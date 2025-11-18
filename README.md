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
- 多种模板样式:渐变背景、纯色、简约风格
- 自动适配平台规格:
  - 小红书: 3:4比例 (1080x1440)
  - Instagram: 1:1比例 (1080x1080)
- 支持水印/Logo添加
- 图片预览和批量下载

### 🚀 批量生成功能
- 手动输入或CSV文件导入多个主题
- 一键批量生成多组内容和图片
- 实时进度显示
- 支持失败重试

### 📊 内容管理
- 完整的生成历史记录
- 按平台、状态、AI模型筛选
- 内容编辑和再生成
- 批量下载图片(ZIP打包)
- 内容状态管理(草稿/已发布/归档)

### ⚙️ 灵活配置
- 独立的AI模型API密钥配置
- 每个模型的参数调整(温度、max_tokens等)
- API连接测试功能
- 自定义图片尺寸和样式
- 自定义内容模板

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
└── languages/                         # 国际化文件
```

## 🔌 REST API端点

插件提供以下REST API端点:

- `POST /wp-json/aiscg/v1/generate` - 生成内容
- `POST /wp-json/aiscg/v1/generate-images` - 生成图片
- `GET /wp-json/aiscg/v1/posts` - 获取内容列表
- `GET /wp-json/aiscg/v1/posts/{id}` - 获取单个内容
- `PUT /wp-json/aiscg/v1/posts/{id}` - 更新内容
- `DELETE /wp-json/aiscg/v1/posts/{id}` - 删除内容
- `POST /wp-json/aiscg/v1/regenerate/{id}` - 重新生成
- `GET /wp-json/aiscg/v1/download-images/{id}` - 下载图片
- `POST /wp-json/aiscg/v1/test-connection` - 测试AI连接
- `GET/POST /wp-json/aiscg/v1/settings` - 获取/保存设置

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

### Version 1.0.0 (2024-01-18)
- ✅ 初始版本发布
- ✅ 支持5个主流AI模型
- ✅ 小红书和Instagram内容生成
- ✅ 自动图片生成
- ✅ 批量生成功能
- ✅ 完整的内容管理系统

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
