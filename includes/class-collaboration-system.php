<?php
/**
 * Collaboration System Class
 *
 * 内容协作和审核工作流管理
 *
 * @package AI_Social_Content_Generator
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_Collaboration_System Class
 */
class AISCG_Collaboration_System {

	/**
	 * 工作流状态
	 *
	 * @var array
	 */
	private $workflow_statuses = array(
		'draft'       => '草稿',
		'pending'     => '待审核',
		'in_review'   => '审核中',
		'approved'    => '已批准',
		'rejected'    => '已拒绝',
		'published'   => '已发布',
		'archived'    => '已归档',
	);

	/**
	 * 用户角色权限
	 *
	 * @var array
	 */
	private $role_permissions = array(
		'administrator' => array( 'create', 'edit', 'delete', 'review', 'approve', 'publish' ),
		'editor'        => array( 'create', 'edit', 'review', 'approve', 'publish' ),
		'author'        => array( 'create', 'edit', 'submit_review' ),
		'contributor'   => array( 'create', 'submit_review' ),
	);

	/**
	 * 日志对象
	 *
	 * @var AISCG_Logger
	 */
	private $logger;

	/**
	 * 数据库操作对象
	 *
	 * @var AISCG_Database
	 */
	private $db;

	/**
	 * 通知对象
	 *
	 * @var AISCG_Notification
	 */
	private $notification;

	/**
	 * 构造函数
	 */
	public function __construct() {
		$this->logger = new AISCG_Logger();
		$this->db = new AISCG_Database();
		$this->notification = new AISCG_Notification();
	}

	/**
	 * 更新内容状态
	 *
	 * @param int    $post_id 内容ID
	 * @param string $new_status 新状态
	 * @param array  $data 附加数据
	 * @return array|false 更新结果
	 */
	public function update_status( $post_id, $new_status, $data = array() ) {
		$post = $this->db->get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$current_user = wp_get_current_user();
		$old_status = isset( $post['status'] ) ? $post['status'] : 'draft';

		// 检查权限
		if ( ! $this->can_change_status( $old_status, $new_status ) ) {
			$this->logger->warning( '状态更改权限不足', array(
				'post_id' => $post_id,
				'user_id' => $current_user->ID,
				'old_status' => $old_status,
				'new_status' => $new_status,
			) );

			return array(
				'success' => false,
				'error' => '您没有权限执行此操作',
			);
		}

		// 更新状态
		$result = $this->db->update_post( $post_id, array(
			'status' => $new_status,
		) );

		if ( ! $result ) {
			return array(
				'success' => false,
				'error' => '状态更新失败',
			);
		}

		// 记录活动
		$this->log_activity( array(
			'post_id' => $post_id,
			'action' => 'status_changed',
			'old_value' => $old_status,
			'new_value' => $new_status,
			'user_id' => $current_user->ID,
			'metadata' => $data,
		) );

		// 发送通知
		$this->send_status_notification( $post_id, $old_status, $new_status, $data );

		$this->logger->info( "内容状态已更新 #{$post_id}", array(
			'old_status' => $old_status,
			'new_status' => $new_status,
			'user_id' => $current_user->ID,
		) );

		return array(
			'success' => true,
			'post_id' => $post_id,
			'old_status' => $old_status,
			'new_status' => $new_status,
		);
	}

	/**
	 * 提交审核
	 *
	 * @param int   $post_id 内容ID
	 * @param array $reviewers 审核者用户ID数组
	 * @param array $options 选项
	 * @return array 提交结果
	 */
	public function submit_for_review( $post_id, $reviewers = array(), $options = array() ) {
		$defaults = array(
			'notes' => '',
			'priority' => 'normal', // low, normal, high, urgent
			'deadline' => '',
		);

		$options = wp_parse_args( $options, $defaults );

		// 更新状态为待审核
		$result = $this->update_status( $post_id, 'pending', array(
			'reviewers' => $reviewers,
			'submit_notes' => $options['notes'],
			'priority' => $options['priority'],
			'deadline' => $options['deadline'],
		) );

		if ( ! $result || ! $result['success'] ) {
			return array(
				'success' => false,
				'error' => '提交审核失败',
			);
		}

		// 通知审核者
		foreach ( $reviewers as $reviewer_id ) {
			$this->notification->send_email(
				array( get_userdata( $reviewer_id )->user_email ),
				'内容待审核',
				$this->get_review_notification_message( $post_id, $options )
			);
		}

		$this->logger->info( "内容已提交审核 #{$post_id}", array(
			'reviewers' => $reviewers,
			'priority' => $options['priority'],
		) );

		return array(
			'success' => true,
			'post_id' => $post_id,
			'reviewers' => $reviewers,
		);
	}

	/**
	 * 添加审核意见
	 *
	 * @param int    $post_id 内容ID
	 * @param string $comment 意见内容
	 * @param array  $options 选项
	 * @return int|false 意见ID或false
	 */
	public function add_review_comment( $post_id, $comment, $options = array() ) {
		global $wpdb;

		$defaults = array(
			'type' => 'comment', // comment, suggestion, issue
			'severity' => 'normal', // low, normal, high
			'status' => 'open', // open, resolved
			'parent_id' => 0, // 用于回复
		);

		$options = wp_parse_args( $options, $defaults );
		$current_user = wp_get_current_user();

		$comment_data = array(
			'post_id' => $post_id,
			'user_id' => $current_user->ID,
			'comment' => sanitize_textarea_field( $comment ),
			'type' => sanitize_text_field( $options['type'] ),
			'severity' => sanitize_text_field( $options['severity'] ),
			'status' => sanitize_text_field( $options['status'] ),
			'parent_id' => absint( $options['parent_id'] ),
			'created_at' => current_time( 'mysql' ),
		);

		// 保存到设置表中（使用JSON存储）
		$comments = $this->get_review_comments( $post_id );
		$comments[] = $comment_data;

		$this->save_review_comments( $post_id, $comments );

		// 记录活动
		$this->log_activity( array(
			'post_id' => $post_id,
			'action' => 'comment_added',
			'user_id' => $current_user->ID,
			'metadata' => $comment_data,
		) );

		// 通知内容创建者
		$post = $this->db->get_post( $post_id );
		if ( $post && $post['user_id'] != $current_user->ID ) {
			$creator = get_userdata( $post['user_id'] );
			if ( $creator ) {
				$this->notification->send_email(
					array( $creator->user_email ),
					'收到新的审核意见',
					"您的内容 #{$post_id} 收到了新的审核意见:\n\n{$comment}"
				);
			}
		}

		$this->logger->info( "添加审核意见 #{$post_id}", array(
			'type' => $options['type'],
			'severity' => $options['severity'],
		) );

		return count( $comments );
	}

	/**
	 * 批准内容
	 *
	 * @param int   $post_id 内容ID
	 * @param array $options 选项
	 * @return array 批准结果
	 */
	public function approve_content( $post_id, $options = array() ) {
		$defaults = array(
			'notes' => '',
			'auto_publish' => false,
		);

		$options = wp_parse_args( $options, $defaults );

		// 更新状态为已批准
		$result = $this->update_status( $post_id, 'approved', array(
			'approval_notes' => $options['notes'],
		) );

		if ( ! $result || ! $result['success'] ) {
			return array(
				'success' => false,
				'error' => '批准失败',
			);
		}

		// 如果启用自动发布
		if ( $options['auto_publish'] ) {
			$publish_result = $this->update_status( $post_id, 'published' );
			if ( $publish_result && $publish_result['success'] ) {
				$result['auto_published'] = true;
			}
		}

		$this->logger->info( "内容已批准 #{$post_id}", array(
			'auto_publish' => $options['auto_publish'],
		) );

		return $result;
	}

	/**
	 * 拒绝内容
	 *
	 * @param int    $post_id 内容ID
	 * @param string $reason 拒绝原因
	 * @param array  $options 选项
	 * @return array 拒绝结果
	 */
	public function reject_content( $post_id, $reason, $options = array() ) {
		$defaults = array(
			'allow_resubmit' => true,
			'suggestions' => array(),
		);

		$options = wp_parse_args( $options, $defaults );

		// 更新状态为已拒绝
		$result = $this->update_status( $post_id, 'rejected', array(
			'rejection_reason' => $reason,
			'allow_resubmit' => $options['allow_resubmit'],
			'suggestions' => $options['suggestions'],
		) );

		if ( ! $result || ! $result['success'] ) {
			return array(
				'success' => false,
				'error' => '拒绝操作失败',
			);
		}

		// 通知创建者
		$post = $this->db->get_post( $post_id );
		if ( $post ) {
			$creator = get_userdata( $post['user_id'] );
			if ( $creator ) {
				$message = "您的内容 #{$post_id} 未通过审核\n\n";
				$message .= "原因: {$reason}\n\n";
				if ( ! empty( $options['suggestions'] ) ) {
					$message .= "改进建议:\n";
					foreach ( $options['suggestions'] as $suggestion ) {
						$message .= "- {$suggestion}\n";
					}
				}

				$this->notification->send_email(
					array( $creator->user_email ),
					'内容审核未通过',
					$message
				);
			}
		}

		$this->logger->info( "内容已拒绝 #{$post_id}", array(
			'reason' => $reason,
		) );

		return $result;
	}

	/**
	 * 获取审核意见
	 *
	 * @param int $post_id 内容ID
	 * @return array 意见列表
	 */
	public function get_review_comments( $post_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiscg_settings';
		$key = "review_comments_{$post_id}";

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( $result ) {
			$comments = json_decode( $result, true );
			return is_array( $comments ) ? $comments : array();
		}

		return array();
	}

	/**
	 * 保存审核意见
	 *
	 * @param int   $post_id 内容ID
	 * @param array $comments 意见数组
	 * @return bool
	 */
	private function save_review_comments( $post_id, $comments ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiscg_settings';
		$key = "review_comments_{$post_id}";
		$value = wp_json_encode( $comments );

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( $exists ) {
			return $wpdb->update(
				$table,
				array( 'setting_value' => $value ),
				array( 'setting_key' => $key ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			return $wpdb->insert(
				$table,
				array(
					'setting_key' => $key,
					'setting_value' => $value,
				),
				array( '%s', '%s' )
			);
		}
	}

	/**
	 * 检查用户是否可以更改状态
	 *
	 * @param string $old_status 旧状态
	 * @param string $new_status 新状态
	 * @return bool
	 */
	private function can_change_status( $old_status, $new_status ) {
		$current_user = wp_get_current_user();
		$user_role = $current_user->roles[0] ?? 'subscriber';

		$permissions = isset( $this->role_permissions[ $user_role ] ) ?
			$this->role_permissions[ $user_role ] : array();

		// 管理员可以执行任何操作
		if ( $user_role === 'administrator' ) {
			return true;
		}

		// 检查具体权限
		$status_change_map = array(
			'draft->pending' => 'submit_review',
			'pending->in_review' => 'review',
			'in_review->approved' => 'approve',
			'in_review->rejected' => 'approve',
			'approved->published' => 'publish',
			'rejected->draft' => 'edit',
		);

		$transition = "{$old_status}->{$new_status}";
		$required_permission = isset( $status_change_map[ $transition ] ) ?
			$status_change_map[ $transition ] : 'edit';

		return in_array( $required_permission, $permissions, true );
	}

	/**
	 * 记录活动日志
	 *
	 * @param array $activity 活动数据
	 * @return bool
	 */
	private function log_activity( $activity ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiscg_settings';
		$key = "collaboration_activity_{$activity['post_id']}";

		// 获取现有活动
		$activities = array();
		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( $result ) {
			$activities = json_decode( $result, true );
			if ( ! is_array( $activities ) ) {
				$activities = array();
			}
		}

		// 添加新活动
		$activity['timestamp'] = current_time( 'mysql' );
		$activities[] = $activity;

		// 只保留最近100条活动
		if ( count( $activities ) > 100 ) {
			$activities = array_slice( $activities, -100 );
		}

		// 保存
		$value = wp_json_encode( $activities );

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( $exists ) {
			return $wpdb->update(
				$table,
				array( 'setting_value' => $value ),
				array( 'setting_key' => $key ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			return $wpdb->insert(
				$table,
				array(
					'setting_key' => $key,
					'setting_value' => $value,
				),
				array( '%s', '%s' )
			);
		}
	}

	/**
	 * 获取活动日志
	 *
	 * @param int $post_id 内容ID
	 * @param int $limit 限制数量
	 * @return array 活动列表
	 */
	public function get_activity_log( $post_id, $limit = 50 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiscg_settings';
		$key = "collaboration_activity_{$post_id}";

		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( $result ) {
			$activities = json_decode( $result, true );
			if ( is_array( $activities ) ) {
				return array_slice( $activities, -$limit );
			}
		}

		return array();
	}

	/**
	 * 发送状态变更通知
	 *
	 * @param int    $post_id 内容ID
	 * @param string $old_status 旧状态
	 * @param string $new_status 新状态
	 * @param array  $data 附加数据
	 */
	private function send_status_notification( $post_id, $old_status, $new_status, $data ) {
		$post = $this->db->get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$creator = get_userdata( $post['user_id'] );
		if ( ! $creator ) {
			return;
		}

		$status_names = $this->workflow_statuses;
		$old_name = isset( $status_names[ $old_status ] ) ? $status_names[ $old_status ] : $old_status;
		$new_name = isset( $status_names[ $new_status ] ) ? $status_names[ $new_status ] : $new_status;

		$subject = "内容状态已更新: {$old_name} → {$new_name}";
		$message = "您的内容 #{$post_id} 状态已从「{$old_name}」变更为「{$new_name}」\n\n";
		$message .= "标题: {$post['title']}\n";

		if ( ! empty( $data ) ) {
			$message .= "\n附加信息:\n";
			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
				$message .= "{$key}: {$value}\n";
			}
		}

		$this->notification->send_email(
			array( $creator->user_email ),
			$subject,
			$message
		);
	}

	/**
	 * 获取审核通知消息
	 *
	 * @param int   $post_id 内容ID
	 * @param array $options 选项
	 * @return string 消息内容
	 */
	private function get_review_notification_message( $post_id, $options ) {
		$post = $this->db->get_post( $post_id );
		$creator = get_userdata( $post['user_id'] );

		$message = "您有新的内容待审核\n\n";
		$message .= "内容ID: #{$post_id}\n";
		$message .= "标题: {$post['title']}\n";
		$message .= "创建者: {$creator->display_name}\n";
		$message .= "优先级: {$options['priority']}\n";

		if ( ! empty( $options['deadline'] ) ) {
			$message .= "截止时间: {$options['deadline']}\n";
		}

		if ( ! empty( $options['notes'] ) ) {
			$message .= "\n备注:\n{$options['notes']}\n";
		}

		return $message;
	}

	/**
	 * 获取工作流统计
	 *
	 * @param array $args 查询参数
	 * @return array 统计数据
	 */
	public function get_workflow_stats( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id' => 0,
			'date_from' => '',
			'date_to' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$table = $wpdb->prefix . 'aiscg_posts';
		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$where_values[] = $args['user_id'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$where_values[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$where_values[] = $args['date_to'];
		}

		$where_clause = implode( ' AND ', $where );

		// 获取各状态数量
		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare(
				"SELECT status, COUNT(*) as count FROM {$table} WHERE {$where_clause} GROUP BY status",
				$where_values
			);
		} else {
			$sql = "SELECT status, COUNT(*) as count FROM {$table} WHERE {$where_clause} GROUP BY status";
		}

		$results = $wpdb->get_results( $sql, ARRAY_A );

		$stats = array(
			'total' => 0,
			'by_status' => array(),
		);

		foreach ( $results as $row ) {
			$status = $row['status'];
			$count = (int) $row['count'];
			$stats['by_status'][ $status ] = $count;
			$stats['total'] += $count;
		}

		// 计算百分比
		foreach ( $stats['by_status'] as $status => $count ) {
			$stats['by_status'][ $status ] = array(
				'count' => $count,
				'percentage' => $stats['total'] > 0 ? round( ( $count / $stats['total'] ) * 100, 1 ) : 0,
			);
		}

		return $stats;
	}

	/**
	 * 批量分配审核者
	 *
	 * @param array $post_ids 内容ID数组
	 * @param array $reviewers 审核者ID数组
	 * @return array 分配结果
	 */
	public function batch_assign_reviewers( $post_ids, $reviewers ) {
		$results = array(
			'success' => 0,
			'failed' => 0,
		);

		foreach ( $post_ids as $post_id ) {
			$result = $this->submit_for_review( $post_id, $reviewers );
			if ( $result && $result['success'] ) {
				$results['success']++;
			} else {
				$results['failed']++;
			}
		}

		$this->logger->info( '批量分配审核者完成', $results );

		return $results;
	}

	/**
	 * 获取我的待办任务
	 *
	 * @param int $user_id 用户ID
	 * @return array 待办任务列表
	 */
	public function get_my_tasks( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		$tasks = array(
			'to_review' => array(), // 待审核
			'my_drafts' => array(), // 我的草稿
			'rejected' => array(),  // 被拒绝
		);

		// 获取待审核的内容（我是审核者）
		// 注意：这需要从settings表中查询review_comments来确定审核者
		// 为简化，这里先获取所有pending/in_review状态的内容
		$pending_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN ('pending', 'in_review') ORDER BY created_at DESC LIMIT 20"
			),
			ARRAY_A
		);

		$tasks['to_review'] = $pending_posts;

		// 获取我的草稿
		$my_drafts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND status = 'draft' ORDER BY updated_at DESC LIMIT 20",
				$user_id
			),
			ARRAY_A
		);

		$tasks['my_drafts'] = $my_drafts;

		// 获取被拒绝的内容
		$rejected = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND status = 'rejected' ORDER BY updated_at DESC LIMIT 20",
				$user_id
			),
			ARRAY_A
		);

		$tasks['rejected'] = $rejected;

		return $tasks;
	}
}
