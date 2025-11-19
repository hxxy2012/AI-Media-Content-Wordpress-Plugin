# AI Social Content Generator - WordPress Plugin

一个强大的WordPress插件,通过多个AI模型(OpenAI GPT、Google Gemini、DeepSeek、Claude、通义千问等)自动生成小红书和Instagram的营销内容及配图。

## ✨ 功能特点

### 🤖 多AI模型支持
- **OpenAI GPT** - GPT-4, GPT-4 Turbo, GPT-3.5 Turbo
- **Google Gemini** - Gemini Pro, Gemini Pro Vision
- **DeepSeek** - DeepSeek Chat, DeepSeek Coder
- **Anthropic Claude** - Claude 3 Opus, Sonnet, Haiku
- **通义千问 (Qwen)** - Qwen Max, Plus, Turbo

### 📝 智能内容生成
- 根据主题或关键词自动生成适合平台的文案
- **小红书风格**: emoji丰富、分段清晰、标签突出
- **Instagram风格**: hashtag合理、文案简洁有吸引力
- 支持自定义prompt模板
- 智能JSON解析,自动提取标题、正文和标签

### 🎨 图片自动生成
- 根据文案内容自动生成1-9张配图
- **7种专业模板样式**:
  - 渐变背景 (紫色到粉色)
  - 纯色背景 (浅蓝色)
  - 简约风格 (纯白)
  - 现代风格 (青色到蓝色,带几何装饰)
  - 深色风格 (深灰渐变,带光效)
  - 彩色风格 (橙-粉-紫三色渐变)
  - 优雅风格 (米白色+金色边框)
- 自动适配平台规格:
  - 小红书: 3:4比例 (1080x1440)
  - Instagram: 1:1比例 (1080x1080)
- 支持水印/Logo添加
- 图片预览和批量下载

### 🚀 批量处理功能
- 手动输入或CSV文件导入多个主题
- 一键批量生成多组内容和图片
- 实时进度跟踪和状态显示
- 批量任务队列管理
- 支持失败重试和错误处理
- 批量结果导出(CSV/JSON)

### 📊 内容管理
- 完整的生成历史记录
- 按平台、状态、AI模型筛选
- 内容编辑和再生成
- 批量下载图片(ZIP打包)
- 内容状态管理(草稿/已发布/归档)
- **版本控制** - 内容历史版本保存和恢复
- **多格式导出** - 支持TXT、JSON、CSV、Markdown、ZIP格式

### ⚙️ 灵活配置
- 独立的AI模型API密钥配置
- 每个模型的参数调整(温度、max_tokens等)
- API连接测试功能
- 自定义图片尺寸和样式
- 自定义内容模板

### 🎨 高级图片定制
- **6种预设配色方案** - 海洋、日落、森林、夜空、粉色梦幻、简约
- **渐变背景** - 线性渐变(水平、垂直、对角)、径向渐变
- **图案装饰** - 圆点、斜线、网格、圆圈等图案
- **边框和阴影** - 可自定义边框宽度、颜色、圆角、阴影效果
- **文字样式** - 自定义字体大小、颜色、对齐方式、行高
- **水印功能** - 支持多位置水印(左上、右上、左下、右下、居中)
- **滤镜效果** - 灰度、复古、模糊、亮度、对比度调整
- **自定义布局** - 内边距、间距、标题边距等全面可调

### 📋 内容模板系统
- **预设模板** - 小红书种草、攻略,Instagram故事、商业推广
- **自定义模板** - 创建、编辑、管理个人模板库
- **模板变量** - 支持变量占位符,快速填充内容
- **模板分类** - 按平台、类别组织模板
- **模板导入/导出** - 分享和备份模板配置
- **模板渲染** - 一键应用模板生成内容

### 🔄 定时任务与自动化
- **定时生成** - 设置每2小时、6小时、12小时、每日定时生成
- **主题池管理** - 预设主题列表,自动轮换使用
- **自动清理** - 自动清理过期内容和孤立图片
- **任务日志** - 记录每次定时任务执行结果

### 📈 统计与分析
- **总览统计** - 总内容数、今日生成、平台分布、AI模型使用率
- **趋势分析** - 30天生成趋势,按日期统计
- **热门标签** - 最常用的Hashtag分析
- **质量指标** - 平均内容长度、标签数量统计
- **生产力分析** - 最活跃的小时和星期统计
- **存储统计** - 图片存储空间使用情况

### 🔍 日志与监控
- **多级日志** - DEBUG、INFO、WARNING、ERROR四个级别
- **自动记录** - API调用、内容生成、错误异常全程记录
- **日志查询** - 按级别、日期、关键词筛选查询
- **日志导出** - 支持CSV、JSON、TXT格式导出
- **日志统计** - 按级别、日期、用户统计分析
- **自动清理** - 可配置日志保留天数,自动清理旧日志

### ⚡ API管理与缓存
- **速率限制** - 智能管理API调用频率,防止超限
- **智能缓存** - 相同prompt自动缓存,节省API费用
- **缓存策略** - 可配置缓存有效期(默认1小时)
- **缓存统计** - 缓存命中率、缓存大小监控
- **API统计** - 按服务、日期、小时统计API调用量
- **缓存管理** - 手动清空缓存、清理过期缓存

### 🔔 通知与提醒
- **Webhook通知** - 支持多个Webhook URL
- **事件过滤** - 可选择触发通知的事件类型
- **签名验证** - HMAC-SHA256签名保证安全性
- **邮件通知** - 重要事件发送邮件提醒
- **通知历史** - 记录所有通知发送历史
- **Webhook测试** - 测试Webhook配置是否正常

### 💾 设置导入/导出
- **完整备份** - 一键导出所有插件设置
- **选择性导出** - 可选是否包含API密钥、模板、Webhook
- **设置导入** - 从备份文件恢复设置
- **版本兼容** - 自动检测设置文件版本兼容性
- **备份管理** - 查看、删除历史备份文件
- **设置重置** - 一键恢复默认设置

### 🎁 WordPress集成
- **4个Shortcode** - [aiscg_content]、[aiscg_gallery]、[aiscg_stats]、[aiscg_latest]
- **2个Widget** - 最新内容Widget、统计Dashboard Widget
- **REST API** - 完整的20+个API端点
- **权限管理** - 基于WordPress用户角色的权限控制

## 📦 安装要求

- WordPress 5.8 或更高版本
- PHP 7.4 或更高版本
- PHP GD库(用于图片生成)
- 至少一个AI服务的API密钥

## 🔧 安装步骤

1. **下载插件**
   ```bash
   git clone https://github.com/hxxy2012/AI-Media-Content-Wordpress-Plugin.git
   ```

2. **上传到WordPress**
   - 将整个文件夹上传到 `/wp-content/plugins/` 目录
   - 或在WordPress后台通过"插件 > 安装插件 > 上传插件"上传ZIP文件

3. **激活插件**
   - 在WordPress后台"插件"页面找到"AI Social Content Generator"
   - 点击"启用"

4. **配置AI模型**
   - 进入 WordPress后台 > 设置 > AI Content Generator
   - 在"AI Models"标签中配置至少一个AI服务的API密钥
   - 点击"Test Connection"验证配置

## 🎯 使用指南

### 1. 配置AI模型

进入 **设置 > AI Content Generator > AI Models**:

1. 选择要使用的AI模型
2. 输入对应的API密钥
3. 配置模型参数:
   - **Temperature**: 控制创意性(0-2,越高越创意)
   - **Max Tokens**: 生成内容的最大长度
4. 点击"Test Connection"测试连接
5. 保存设置

#### 获取API密钥:

- **OpenAI**: https://platform.openai.com/api-keys
- **Google Gemini**: https://ai.google.dev/
- **DeepSeek**: https://platform.deepseek.com/
- **Anthropic Claude**: https://console.anthropic.com/
- **通义千问**: https://dashscope.aliyuncs.com/

### 2. 生成内容

进入 **AI Content > Generate**:

#### 单个生成:
1. 选择平台(小红书/Instagram)
2. 选择AI模型(或使用默认)
3. 输入主题或关键词
4. (可选)输入自定义prompt
5. 设置图片数量(1-9张)
6. 点击"Generate Content"

#### 批量生成:
1. 切换到"Batch Generation"模式
2. 手动输入主题(一行一个)或上传CSV文件
3. 点击"Start Batch Generation"
4. 等待批量生成完成

### 3. 管理内容

进入 **AI Content > History**:

- **查看**: 点击"View"查看完整内容和图片
- **编辑**: 修改标题、正文、标签和状态
- **重新生成**: 使用相同主题重新生成
- **下载**: 下载单张或所有图片
- **删除**: 删除不需要的内容

### 4. 自定义设置

#### 图片设置 (Settings > Image Settings):
- 配置每个平台的图片尺寸
- 选择默认模板样式
- 设置字体和水印

#### 内容模板 (Settings > Content Templates):
- 自定义小红书和Instagram的prompt模板
- 使用 `{topic}` 作为主题占位符

#### 高级设置 (Settings > Advanced):
- 设置请求超时时间
- 配置并发请求数
- 启用调试日志

## 📁 项目结构

```
ai-social-content-generator/
├── ai-social-content-generator.php    # 主插件文件
├── includes/
│   ├── class-plugin.php               # 核心插件类
│   ├── class-activator.php            # 激活处理
│   ├── class-deactivator.php          # 停用处理
│   ├── class-database.php             # 数据库操作
│   ├── class-content-generator.php    # 内容生成器
│   ├── class-image-generator.php      # 图片生成器
│   ├── class-batch-processor.php      # 批量处理器
│   ├── class-scheduler.php            # 定时任务
│   ├── class-content-exporter.php     # 内容导出器
│   ├── class-analytics.php            # 统计分析
│   ├── class-shortcodes.php           # 短代码
│   ├── class-widget.php               # Widget组件
│   ├── class-logger.php               # 日志系统
│   ├── class-template-manager.php     # 模板管理器
│   ├── class-version-control.php      # 版本控制
│   ├── class-image-customizer.php     # 图片定制器
│   ├── class-api-manager.php          # API管理器
│   ├── class-notification.php         # 通知系统
│   ├── class-settings-manager.php     # 设置管理器
│   └── ai-services/                   # AI服务
│       ├── interface-ai-service.php
│       ├── class-ai-service-factory.php
│       ├── class-openai-service.php
│       ├── class-gemini-service.php
│       ├── class-deepseek-service.php
│       ├── class-claude-service.php
│       └── class-qwen-service.php
├── admin/
│   ├── class-admin.php                # 管理界面
│   ├── class-settings.php             # 设置页面
│   ├── views/                         # 视图文件
│   │   ├── generator-page.php
│   │   └── history-page.php
│   ├── js/
│   │   └── admin.js                   # JavaScript
│   └── css/
│       └── admin.css                  # CSS样式
├── assets/                            # 静态资源
│   ├── fonts/
│   ├── templates/
│   └── images/
├── languages/                         # 国际化文件
└── uninstall.php                      # 卸载脚本
```

## 🔌 REST API端点

插件提供以下REST API端点:

**内容管理**
- `POST /wp-json/aiscg/v1/generate` - 生成内容
- `POST /wp-json/aiscg/v1/generate-images` - 生成图片
- `GET /wp-json/aiscg/v1/posts` - 获取内容列表
- `GET /wp-json/aiscg/v1/posts/{id}` - 获取单个内容
- `PUT /wp-json/aiscg/v1/posts/{id}` - 更新内容
- `DELETE /wp-json/aiscg/v1/posts/{id}` - 删除内容
- `POST /wp-json/aiscg/v1/regenerate/{id}` - 重新生成

**批量处理**
- `POST /wp-json/aiscg/v1/batch-generate` - 批量生成
- `GET /wp-json/aiscg/v1/batch-status/{id}` - 批量任务状态

**导出功能**
- `POST /wp-json/aiscg/v1/export` - 导出内容
- `GET /wp-json/aiscg/v1/download-images/{id}` - 下载图片

**统计分析**
- `GET /wp-json/aiscg/v1/statistics` - 获取统计数据
- `GET /wp-json/aiscg/v1/analytics` - 获取分析报告

**定时任务**
- `GET /wp-json/aiscg/v1/scheduler` - 获取调度状态
- `POST /wp-json/aiscg/v1/scheduler` - 保存调度配置

**模板管理**
- `GET /wp-json/aiscg/v1/templates` - 获取模板列表
- `POST /wp-json/aiscg/v1/templates` - 创建模板
- `PUT /wp-json/aiscg/v1/templates/{id}` - 更新模板
- `DELETE /wp-json/aiscg/v1/templates/{id}` - 删除模板

**版本控制**
- `GET /wp-json/aiscg/v1/versions/{post_id}` - 获取版本历史
- `POST /wp-json/aiscg/v1/restore-version/{version_id}` - 恢复版本

**日志与缓存**
- `GET /wp-json/aiscg/v1/logs` - 获取日志
- `POST /wp-json/aiscg/v1/clear-cache` - 清空缓存

**设置管理**
- `GET/POST /wp-json/aiscg/v1/settings` - 获取/保存设置
- `POST /wp-json/aiscg/v1/test-connection` - 测试AI连接
- `POST /wp-json/aiscg/v1/export-settings` - 导出设置
- `POST /wp-json/aiscg/v1/import-settings` - 导入设置

## 🗄️ 数据库结构

### wp_aiscg_posts - 内容表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| platform | varchar(20) | 平台(xiaohongshu/instagram) |
| title | text | 标题 |
| content | text | 正文 |
| hashtags | text | 标签(JSON) |
| ai_model | varchar(50) | 使用的AI模型 |
| prompt_used | text | 使用的prompt |
| status | varchar(20) | 状态(draft/published/archived) |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### wp_aiscg_images - 图片表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| post_id | bigint | 关联内容ID |
| image_url | text | 图片URL |
| image_path | text | 图片路径 |
| image_order | int | 图片顺序 |
| width | int | 宽度 |
| height | int | 高度 |
| created_at | datetime | 创建时间 |

### wp_aiscg_settings - 设置表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| setting_key | varchar(100) | 设置键(唯一) |
| setting_value | longtext | 设置值 |
| updated_at | datetime | 更新时间 |

### wp_aiscg_logs - 日志表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| level | varchar(20) | 日志级别(DEBUG/INFO/WARNING/ERROR) |
| message | text | 日志消息 |
| context | longtext | 上下文数据(JSON) |
| user_id | bigint | 用户ID |
| ip_address | varchar(45) | IP地址 |
| created_at | datetime | 创建时间 |

### wp_aiscg_templates - 模板表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| name | varchar(200) | 模板名称 |
| description | text | 模板描述 |
| platform | varchar(20) | 平台 |
| content | longtext | 模板内容 |
| variables | longtext | 变量列表(JSON) |
| category | varchar(50) | 分类 |
| is_active | tinyint(1) | 是否启用 |
| user_id | bigint | 创建者ID |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### wp_aiscg_versions - 版本控制表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| post_id | bigint | 内容ID |
| title | text | 标题 |
| content | text | 正文 |
| hashtags | text | 标签 |
| platform | varchar(20) | 平台 |
| ai_model | varchar(50) | AI模型 |
| images | longtext | 图片数据(JSON) |
| metadata | longtext | 元数据(JSON) |
| changes | longtext | 变更说明(JSON) |
| user_id | bigint | 用户ID |
| created_at | datetime | 创建时间 |

### wp_aiscg_rate_limits - 速率限制表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| service | varchar(50) | AI服务名称 |
| metadata | longtext | 元数据(JSON) |
| user_id | bigint | 用户ID |
| created_at | datetime | 创建时间 |

### wp_aiscg_cache - 缓存表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| cache_key | varchar(255) | 缓存键(唯一) |
| cache_value | longtext | 缓存值 |
| expires_at | datetime | 过期时间 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### wp_aiscg_notifications - 通知表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| type | varchar(20) | 通知类型(webhook/email) |
| recipient | text | 接收者 |
| success | tinyint(1) | 是否成功 |
| metadata | longtext | 元数据(JSON) |
| created_at | datetime | 创建时间 |

## 🔒 安全性

- API密钥加密存储
- 所有用户输入经过sanitize处理
- WordPress nonce验证
- 权限检查(仅管理员可访问)
- SQL注入防护
- XSS防护

## ⚡ 性能优化

- 异步请求处理
- 请求队列避免API限流
- 图片生成考虑服务器资源
- 错误处理和重试机制
- 详细的日志记录(可选)

## 🐛 故障排除

### 无法生成内容
1. 检查AI模型是否已配置并启用
2. 验证API密钥是否正确
3. 使用"Test Connection"测试连接
4. 检查WordPress错误日志

### 图片生成失败
1. 确认PHP GD库已安装
2. 检查上传目录权限
3. 查看服务器内存限制
4. 启用调试日志查看详细错误

### API请求超时
1. 增加"Request Timeout"设置
2. 检查网络连接
3. 降低max_tokens值
4. 使用更快的AI模型

## 📝 更新日志

### Version 1.1.0 (2025-01-19)
- ✅ 新增日志系统 - 完整的错误追踪和调试功能
- ✅ 新增模板管理 - 自定义内容模板系统
- ✅ 新增版本控制 - 内容历史版本保存和恢复
- ✅ 新增图片定制 - 高级图片自定义选项
- ✅ 新增API管理 - 速率限制和智能缓存
- ✅ 新增通知系统 - Webhook和邮件通知
- ✅ 新增设置管理 - 导入/导出配置文件
- ✅ 新增6个数据库表 - 支持新功能
- ✅ 新增30+ REST API端点
- ✅ 完善文档和代码注释

### Version 1.0.0 (2024-01-18)
- ✅ 初始版本发布
- ✅ 支持5个主流AI模型
- ✅ 小红书和Instagram内容生成
- ✅ 自动图片生成(7种模板)
- ✅ 批量生成功能
- ✅ 完整的内容管理系统
- ✅ 定时任务调度
- ✅ 统计分析Dashboard
- ✅ Shortcode和Widget支持

## 🤝 贡献

欢迎贡献代码、报告问题或提出新功能建议!

1. Fork项目
2. 创建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启Pull Request

## 📄 许可证

本项目采用 GPL v2 或更高版本许可证。详见 LICENSE 文件。

## 👨‍💻 作者

- GitHub: [@hxxy2012](https://github.com/hxxy2012)

## 🙏 致谢

- OpenAI, Google, DeepSeek, Anthropic, Alibaba - 提供强大的AI API
- WordPress社区 - 提供优秀的开发框架

## 📮 支持

如有问题或建议,请:
- 提交Issue: https://github.com/hxxy2012/AI-Media-Content-Wordpress-Plugin/issues
- 查看文档: https://github.com/hxxy2012/AI-Media-Content-Wordpress-Plugin/wiki

---

**注意**: 使用本插件需要自行申请并承担AI服务的API费用。请合理使用,注意成本控制。
