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

### 📊 内容质量评分与优化
- **6维度质量评分** - 可读性、长度、标签、互动潜力、关键词、结构
- **A-D等级评定** - 自动计算内容质量等级
- **智能优化建议** - 针对性改进建议
- **AI自动优化** - 一键AI优化低分内容
- **A/B测试** - 对比优化前后效果
- **质量报告** - 生成详细的质量分析报告

### 🌍 多语言内容生成
- **14种语言支持** - 中文(简繁)、英语、日语、韩语、西班牙语、法语、德语、意大利语、葡萄牙语、俄语、阿拉伯语、泰语、越南语
- **原生内容生成** - 直接使用目标语言生成内容
- **智能翻译** - 保持风格的专业翻译
- **本地化标签** - 自动适配目标语言热门标签
- **语言检测** - 自动识别内容语言
- **多语言变体** - 一键生成多语言版本

### 📚 素材库管理
- **5种素材类型** - 文案片段、图片模板、标签集、开场白、结尾语
- **智能分类** - 按平台、类别、语言组织
- **使用统计** - 追踪素材使用频率
- **内置模板** - 预设高质量素材模板
- **快速搜索** - 关键词搜索素材
- **标签系统** - 灵活的标签分类

### 📅 智能发布计划
- **智能日历生成** - 自动规划最佳发布时间
- **平台最佳时段** - 根据平台特点推荐发布时间
- **自动发布** - 定时自动发布内容
- **时区支持** - 多时区发布计划
- **发布通知** - 发布成功自动通知
- **可视化日历** - 直观的日历视图

### 📈 内容趋势分析
- **热门话题检测** - 自动识别热门主题
- **标签趋势分析** - 追踪热门标签变化
- **内容模式分析** - emoji使用、开场方式、平均长度统计
- **时间分布分析** - 发布高峰时段和星期
- **增长率计算** - 话题和标签增长趋势
- **智能推荐** - 基于趋势的内容建议

### 🔍 SEO深度优化
- **多维SEO分析** - 标题、内容、关键词、标签、可读性全面分析
- **关键词提取** - 自动提取核心关键词
- **密度计算** - 关键词密度优化建议
- **LSI关键词** - 相关关键词推荐
- **AI优化** - 智能SEO优化重写
- **优化评分** - 0-100分SEO评分系统

### 👥 团队协作与审核
- **工作流管理** - 草稿→待审核→审核中→已批准→已发布
- **多角色权限** - 管理员、编辑、作者、贡献者角色权限
- **审核意见** - 添加评论、建议和问题
- **批准/拒绝** - 完整的审批流程
- **活动日志** - 记录所有协作活动
- **任务管理** - 我的待办、待审核列表
- **邮件通知** - 状态变更自动通知

### 📊 高级数据报表
- **8种报表类型** - 总览、性能、趋势、对比、质量、生产力、SEO、多语言
- **可视化图表** - 直观的数据可视化
- **多格式导出** - CSV、JSON、HTML格式导出
- **时间段对比** - 周期环比分析
- **用户生产力** - 团队成员效率统计
- **质量分布** - 内容等级分布统计
- **自定义筛选** - 灵活的数据筛选条件

### ⚡ 性能监控与优化
- **实时性能跟踪** - 监控执行时间和内存使用
- **数据库优化** - 慢查询检测和优化
- **API性能监控** - API调用时间和成功率
- **缓存命中率** - 缓存效率统计
- **系统资源监控** - 内存、CPU、存储监控
- **性能建议** - 智能优化建议
- **自动清理** - 过期缓存和旧日志自动清理
- **数据库优化** - 一键优化所有表

### 🎯 智能内容推荐
- **6种推荐类型** - 热门话题、最佳发布时间、标签建议、内容创意、平台策略、优化建议
- **数据驱动决策** - 基于历史数据和趋势分析
- **个性化建议** - 根据用户内容表现定制
- **趋势预测** - 识别上升中的热门话题
- **最佳时机推荐** - 智能推荐最佳发布时间
- **内容创意灵感** - AI生成创作灵感

### 🏷️ AI智能标签生成
- **AI驱动生成** - 使用大模型智能生成相关标签
- **多策略混合** - trending(热门)、niche(细分)、mixed(混合)三种策略
- **历史数据分析** - 基于高表现内容的标签
- **标签性能追踪** - 分析每个标签的效果
- **平台优化** - 针对小红书和Instagram优化数量和类型
- **批量生成** - 支持批量内容标签生成

### 💰 API成本管理
- **实时成本追踪** - 精确记录每次API调用成本
- **12种模型定价** - 覆盖GPT、Gemini、Claude、DeepSeek、Qwen全系列
- **预算管理** - 设置日/月预算限制
- **成本预警** - 达到预算阈值自动通知
- **多维度统计** - 按模型、时间、用户统计成本
- **成本估算** - 生成前预估API费用
- **导出报告** - CSV/JSON格式成本报告

### 🗂️ 内容智能分类
- **12个预设分类** - 生活、美妆、时尚、美食、旅游、健身、科技、教育、娱乐、家居、母婴、宠物
- **AI+关键词混合** - 结合AI分类和关键词匹配
- **内容类型识别** - 教程、测评、经验、展示、vlog、技巧
- **主题提取** - 自动提取内容主题标签
- **批量分类** - 支持批量内容分类
- **自定义分类** - 添加自定义分类类别
- **分类统计** - 内容分类分布统计

### 🧪 A/B测试系统
- **多种测试类型** - 内容、标签、风格、发布时间测试
- **科学统计分析** - t检验确保结果可靠性
- **自动宣布获胜者** - 达到显著性水平自动选出最优方案
- **可视化报告** - 图表展示测试结果
- **测试管理** - 草稿、运行、暂停、完成全流程管理
- **置信度计算** - 统计显著性和置信区间
- **改进百分比** - 量化优化效果

### 🔍 内容质量检测
- **6大检测维度** - 敏感词、平台规范、格式、可读性、SEO、AI质量
- **实时质量评分** - 0-100分质量评分系统
- **发布前检查** - 自动检测是否可以发布
- **智能修复建议** - 针对每个问题提供修复方案
- **自动修复** - 支持一键自动修复常见问题
- **批量检测** - 批量检测多个内容
- **统计分析** - 常见问题统计和分析

### ✍️ 智能写作助手
- **8种写作辅助** - 继续写作、扩展句子、生成段落、优化标题、生成开场/结尾、过渡语句、换个说法
- **AI驱动建议** - 使用大模型提供多个创作建议
- **语气控制** - 支持休闲、专业、友好等多种语气
- **智能改写** - 改进、简化、正式化、口语化
- **语法检查** - 自动检测语法和用词错误
- **写作模板** - 提供多种常用写作模板
- **实时建议** - 写作过程中实时获取AI建议

### 🔍 关键词研究工具
- **关键词发现** - 从历史内容中挖掘关键词机会
- **趋势分析** - 追踪关键词增长趋势和热度变化
- **难度评估** - 计算关键词竞争难度(0-100分)
- **相关关键词** - AI+统计混合方法生成相关词
- **长尾关键词** - 智能建议3-6词的长尾变体
- **关键词分组** - 按相似度和主题自动分组
- **表现追踪** - 追踪关键词使用频率和效果
- **AI洞察** - 获取关键词营销洞察和建议

### 💾 备份与恢复系统
- **完整备份** - 一键备份内容、图片、设置、模板
- **增量备份** - 仅备份自上次以来的变更
- **选择性恢复** - 灵活选择恢复哪些内容
- **导入导出** - 备份文件导入导出功能
- **压缩存储** - Gzip压缩节省存储空间
- **自动清理** - 自动清理指定天数的旧备份
- **安全保护** - .htaccess保护备份目录
- **版本兼容** - 自动检测备份版本兼容性

### ⚙️ 工作流自动化引擎
- **6种触发器** - 手动、定时、内容创建/更新、质量检查失败、备份完成
- **11种自动化动作** - 生成内容、优化、质量检测、发布、通知、备份、导出、标签、分类、翻译、自定义代码
- **工作流编排** - 可视化定义多步骤自动化流程
- **条件执行** - 支持错误继续、条件分支
- **定时调度** - 每小时/每天/每周自动运行
- **执行历史** - 完整记录每次工作流执行结果
- **事件驱动** - 基于WordPress钩子自动触发
- **异步执行** - 后台异步执行不阻塞主流程

### 🌐 社交媒体分发系统
- **7平台支持** - 小红书、Instagram、微博、抖音、Twitter、Facebook、LinkedIn
- **一键分发** - 同时发布到多个平台
- **Instagram集成** - 完整Graph API集成,支持单图和轮播发布
- **微博集成** - 官方API集成,支持文字和图片微博
- **计划发布** - 设置未来发布时间自动发布
- **发布历史** - 完整记录所有分发记录和状态
- **平台认证** - 安全的OAuth令牌管理
- **批量分发** - 批量发布多个内容到多个平台

### 📊 内容表现分析
- **实时数据同步** - 从Instagram、微博等平台同步表现数据
- **10种核心指标** - 浏览量、点赞、评论、分享、收藏、点击、触达、曝光、互动率、新增粉丝
- **表现报告** - 详细的内容表现分析报告
- **平台对比** - 对比不同平台的内容表现
- **ROI计算** - 自动计算内容投资回报率
- **表现评分** - 0-100分综合表现评分
- **智能建议** - 基于数据的优化建议
- **趋势分析** - 按日/周/月追踪表现趋势
- **自动同步** - 每小时自动同步最新数据

### 📅 智能内容日历
- **可视化日历** - 直观的日历视图管理内容计划
- **多种事件类型** - 内容发布、草稿、活动、提醒、里程碑
- **重复事件** - 支持每天/每周/每月重复规则
- **智能生成** - AI自动生成内容发布计划
- **冲突检测** - 自动检测同一时间段的冲突
- **即将到来** - 显示未来N天的待办事件
- **统计分析** - 日历事件统计和最忙日期分析
- **多平台管理** - 为不同平台规划不同内容
- **颜色标记** - 自定义颜色标记区分事件类型
- **批量操作** - 批量创建、编辑、删除事件

### 🔬 竞品内容分析
- **8维度分析** - 内容策略、发布频率、内容类型、话题分布、标签策略、互动模式、发布时间、内容质量
- **智能洞察** - AI生成竞品核心优势和差异化建议
- **表现对比** - 对比多个竞品的关键指标
- **发布频率分析** - 每日发布量、一致性得分、最活跃日期
- **话题挖掘** - 自动提取竞品Top话题和关键词
- **标签策略** - 分析竞品标签使用模式和多样性
- **互动分析** - 平均点赞/评论/分享,峰值内容识别
- **优化建议** - 基于竞品数据生成可执行建议
- **历史报告** - 保存并追踪竞品分析历史

### ⚖️ 内容合规检查
- **6大检查维度** - 法律合规、版权、广告法、平台政策、内容分级、数据隐私
- **广告法检测** - 自动识别极限词、绝对化用语、虚假承诺
- **平台规则** - 小红书/Instagram等平台特定规则检查
- **敏感词检测** - 政治敏感、暴力血腥、色情低俗内容过滤
- **版权保护** - 品牌提及检查、转载来源验证
- **隐私保护** - 个人信息(手机/身份证/邮箱)泄露检测
- **合规评分** - 0-100分合规得分,多级风险标记
- **AI深度检查** - 使用大模型进行深度合规审查
- **智能建议** - 针对每个违规项提供修复建议和替代词
- **发布拦截** - 严重违规自动拦截发布

### 🤖 智能问答机器人
- **5种机器人类型** - 内容创作助手、客户服务、平台操作指南、SEO顾问、通用问答
- **AI驱动对话** - 基于大模型的自然语言理解和生成
- **上下文记忆** - 保持对话上下文,支持多轮对话
- **意图识别** - 自动识别用户意图(问候/求助/推荐/对比/解释)
- **会话管理** - 创建、更新、结束会话,完整会话历史
- **快速回复** - 预设常见问题快速回复
- **评价系统** - 用户对回复进行评价和反馈
- **使用统计** - 会话数、消息数、平均对话轮数统计
- **多用户支持** - 每个用户独立的会话和历史记录

### 🧠 智能内容推荐系统
- **综合分析** - 整合历史表现、竞品策略、合规要求、热门趋势
- **数据驱动推荐** - 基于真实数据生成内容策略建议
- **AI深度洞察** - 使用大模型生成专业分析和行动计划
- **8大推荐模块** - 内容策略、发布时间、标签策略、内容格式、热门话题、合规指导、竞品对标、行动计划
- **个性化创意** - 针对特定主题生成多个内容创意建议
- **历史表现分析** - 分析30天内容数据,提取成功要素
- **竞品对标** - 对比竞品策略,识别优化机会
- **趋势追踪** - 实时追踪热门话题和标签
- **优先级评分** - 为每个建议提供优先级和预期效果

### ⚡ 自动化内容优化引擎
- **一键优化** - 自动检测并修复内容问题
- **4大优化维度** - 合规修复、质量提升、SEO优化、可读性改善
- **智能合规修复** - 自动替换广告法违禁词(50+词库)、移除隐私信息、清理违规内容
- **质量自动提升** - AI扩充内容、添加emoji、优化段落结构、增加互动引导
- **SEO智能优化** - 优化标题长度、增强标签策略、提取关键词
- **可读性改善** - 清理格式、统一标点、改善段落结构
- **批量处理** - 支持批量优化多个内容,提高效率
- **改善评分** - 量化评估优化效果(0-100分)
- **AI内容扩充** - 使用大模型智能扩充短内容
- **安全替代词库** - 智能替换违禁词为合规表达

### 🎁 WordPress集成
- **4个Shortcode** - [aiscg_content]、[aiscg_gallery]、[aiscg_stats]、[aiscg_latest]
- **2个Widget** - 最新内容Widget、统计Dashboard Widget
- **REST API** - 完整的40+个API端点
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
│   ├── class-content-scorer.php       # 内容质量评分
│   ├── class-content-optimizer.php    # 内容优化器
│   ├── class-multilingual-generator.php # 多语言生成器
│   ├── class-media-library.php        # 素材库管理
│   ├── class-publishing-planner.php   # 发布计划
│   ├── class-trend-analyzer.php       # 趋势分析器
│   ├── class-seo-optimizer.php        # SEO优化器
│   ├── class-collaboration-system.php # 协作系统
│   ├── class-advanced-reports.php     # 高级报表
│   ├── class-performance-monitor.php  # 性能监控
│   ├── class-content-recommender.php  # 智能推荐引擎
│   ├── class-ai-tag-generator.php     # AI标签生成器
│   ├── class-cost-tracker.php         # API成本追踪
│   ├── class-content-classifier.php   # 内容分类器
│   ├── class-ab-test-manager.php      # A/B测试管理
│   ├── class-quality-detector.php     # 质量检测器
│   ├── class-writing-assistant.php    # 智能写作助手
│   ├── class-keyword-researcher.php   # 关键词研究工具
│   ├── class-backup-manager.php       # 备份管理器
│   ├── class-workflow-engine.php      # 工作流自动化引擎
│   ├── class-social-distributor.php   # 社交媒体分发系统
│   ├── class-performance-analyzer.php # 内容表现分析器
│   ├── class-content-calendar.php     # 智能内容日历
│   ├── class-competitor-analyzer.php  # 竞品内容分析器
│   ├── class-compliance-checker.php   # 内容合规检查器
│   ├── class-chatbot.php              # 智能问答机器人
│   ├── class-content-intelligence.php # 智能内容推荐系统
│   ├── class-auto-optimizer.php       # 自动化内容优化引擎
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

### wp_aiscg_media_library - 素材库表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| type | varchar(50) | 素材类型(snippet/hashtag_set/opening/closing) |
| title | varchar(200) | 标题 |
| content | longtext | 内容 |
| category | varchar(50) | 分类 |
| platform | varchar(20) | 平台 |
| language | varchar(10) | 语言 |
| tags | longtext | 标签(JSON) |
| metadata | longtext | 元数据(JSON) |
| usage_count | int | 使用次数 |
| user_id | bigint | 创建者ID |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### wp_aiscg_publishing_plans - 发布计划表
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| post_id | bigint | 内容ID |
| platform | varchar(20) | 平台 |
| scheduled_time | datetime | 计划发布时间 |
| timezone | varchar(50) | 时区 |
| status | varchar(20) | 状态(pending/published/failed) |
| auto_publish | tinyint(1) | 是否自动发布 |
| notify_on_publish | tinyint(1) | 发布后是否通知 |
| published_at | datetime | 实际发布时间 |
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

### Version 1.8.0 (2025-01-20)
- ✅ 新增智能内容推荐系统 - 8大推荐模块，数据驱动，AI洞察，个性化创意建议
- ✅ 新增自动化内容优化引擎 - 4大优化维度，一键修复，批量处理，改善评分
- ✅ 新增2个核心类文件 (content-intelligence, auto-optimizer)
- ✅ 新增4个REST API端点 (智能推荐2个，自动优化2个)
- ✅ 智能推荐：历史分析、竞品对标、趋势追踪、策略建议
- ✅ 自动优化：合规修复、质量提升、SEO优化、可读性改善
- ✅ 完善AI辅助创作全流程，实现从策略到优化的智能化闭环

### Version 1.7.0 (2025-01-20)
- ✅ 新增竞品内容分析器 - 8维度分析(策略/频率/类型/话题/标签/互动/时间/质量),AI洞察
- ✅ 新增内容合规检查器 - 6大检查(法律/版权/广告法/平台政策/分级/隐私),合规评分,智能建议
- ✅ 新增智能问答机器人 - 5种机器人类型,AI对话,意图识别,上下文记忆,会话管理
- ✅ 新增3个核心类文件
- ✅ 新增5个数据库表 (competitors, competitor_reports, compliance_records, chatbot_sessions, chatbot_messages)
- ✅ 完善内容风险管理和用户服务能力
- ✅ 打造智能化内容运营全栈平台

### Version 1.6.0 (2025-01-20)
- ✅ 新增社交媒体分发系统 - 支持7大平台,Instagram/微博官方API集成,一键分发
- ✅ 新增内容表现分析器 - 10种核心指标,实时数据同步,ROI计算,智能建议
- ✅ 新增智能内容日历 - 可视化日历,重复事件,智能生成计划,冲突检测
- ✅ 新增3个核心类文件
- ✅ 新增3个数据库表 (distributions, performance, calendar_events)
- ✅ 完善内容营销全流程闭环
- ✅ 实现数据驱动的内容运营

### Version 1.5.0 (2025-01-20)
- ✅ 新增关键词研究工具 - 关键词发现、趋势分析、难度评估、相关词和长尾词建议
- ✅ 新增备份与恢复系统 - 完整/增量备份、选择性恢复、压缩存储、自动清理
- ✅ 新增工作流自动化引擎 - 6种触发器、11种自动化动作、定时调度、事件驱动
- ✅ 新增3个核心类文件
- ✅ 新增2个数据库表 (workflows, workflow_runs, backups)
- ✅ 完善SEO优化能力和数据管理
- ✅ 实现全流程自动化支持

### Version 1.4.0 (2025-01-20)
- ✅ 新增内容质量检测器 - 6大检测维度，实时质量评分，智能修复建议
- ✅ 新增智能写作助手 - 8种写作辅助，AI驱动建议，智能改写
- ✅ 新增2个核心类文件
- ✅ 完善内容创作全流程支持
- ✅ 提升内容质量和写作效率

### Version 1.3.0 (2025-01-20)
- ✅ 新增智能内容推荐引擎 - 6种推荐类型，数据驱动决策
- ✅ 新增AI智能标签生成器 - AI驱动，多策略混合
- ✅ 新增API成本追踪系统 - 实时追踪，预算管理，成本预警
- ✅ 新增内容智能分类器 - 12个分类，AI+关键词混合
- ✅ 新增A/B测试管理系统 - 科学统计分析，自动选择获胜者
- ✅ 新增5个核心类文件
- ✅ 完善插件智能化和自动化能力
- ✅ 打造全功能AI内容创作平台

### Version 1.2.0 (2025-01-20)
- ✅ 新增内容质量评分系统 - 6维度智能评分
- ✅ 新增内容优化器 - AI驱动的内容优化
- ✅ 新增多语言生成器 - 支持14种语言
- ✅ 新增素材库管理 - 5种素材类型管理
- ✅ 新增智能发布计划 - 自动规划最佳发布时间
- ✅ 新增趋势分析器 - 热门话题和标签追踪
- ✅ 新增SEO优化器 - 深度SEO分析和优化
- ✅ 新增团队协作系统 - 完整工作流和审核
- ✅ 新增高级报表系统 - 8种专业报表
- ✅ 新增性能监控 - 实时性能追踪和优化
- ✅ 新增2个数据库表 - media_library, publishing_plans
- ✅ 扩展REST API至40+端点
- ✅ 打造企业级内容管理平台

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
