# YearlySummary - Typecho 年度文章统计插件

<p align="center">
  <img src="https://img.shields.io/badge/Typecho-1.2+-green.svg" alt="Typecho">
  <img src="https://img.shields.io/badge/PHP-7.4+-blue.svg" alt="PHP">
  <img src="https://img.shields.io/badge/License-MIT-orange.svg" alt="License">
  <img src="https://img.shields.io/badge/Version-1.1.0-brightgreen.svg" alt="Version">
</p>

一款功能强大的 Typecho 年度文章统计插件，帮助博主全面了解自己的写作数据，支持多维度统计分析和数据可视化。

## 功能特性

### 数据概览
- 文章总数统计
- 总字数 / 平均字数统计
- 总评论数 / 平均评论数统计
- 总浏览量统计

### 时间维度分析
- 按月统计文章数量（折线图展示）
- 按周统计文章数量
- 发布时段分布（凌晨/上午/下午/晚上）

### 内容分析
- 最长文章 / 最短文章
- 分类分布（饼图展示）
- 标签使用分布

### 热度排行
- 浏览量排行 TOP N
- 评论数排行 TOP N
- 活跃评论者排行

### 年度对比
- 与上一年数据对比
- 自动计算增长率
- 支持文章数、字数、评论数、浏览量对比

## 安装方法

### 方法一：直接下载

1. 下载最新版本的压缩包
2. 解压后将 `YearlySummary` 文件夹上传到 `/usr/plugins/` 目录
3. 登录 Typecho 后台，进入「控制台」→「插件」
4. 找到「YearlySummary」插件，点击「启用」

### 方法二：Git Clone

```bash
cd /path/to/typecho/usr/plugins/
git clone https://github.com/xiangmingya/YearlySummary.git
```

然后在后台启用插件即可。

## 使用说明

### 查看统计

1. 启用插件后，在「控制台」菜单中会出现「年度统计」选项
2. 点击进入统计页面
3. 使用顶部下拉框选择要统计的年份
4. 页面会自动展示该年份的所有统计数据

### 插件配置

在「插件」→「YearlySummary」→「设置」中可以配置：

| 配置项 | 说明 | 默认值 |
|--------|------|--------|
| 排行榜显示条数 | 设置排行榜显示的条目数量 | 10 |
| 默认统计年份 | 设置默认统计的年份 | 当前年份 |
| 是否统计草稿 | 是否将草稿文章纳入统计 | 否 |
| 图表主题色 | 设置图表的主题颜色 | #667eea |

## 文件结构

```
YearlySummary/
├── Plugin.php          # 插件主文件
├── Panel.php           # 统计面板页面
├── assets/
│   └── css/
│       └── style.css   # 样式文件
└── README.md           # 说明文档
```

## 系统要求

- Typecho 1.2 或更高版本
- PHP 7.4 或更高版本

## 注意事项

1. **浏览量统计**：浏览量数据需要安装支持 `views` 字段的浏览统计插件（如 ViewsCounter），否则浏览量相关数据将显示为 0。

2. **数据准确性**：统计数据基于文章的发布时间（created 字段），修改文章不会影响其所属年份。

## 更新日志

### v1.1.0 (2025-12-23)

- 优化：数据查询效率改进
- 优化：统一版本号管理
- 移除：更新检查功能

### v1.0.0 (2025-12-22)

- 初始版本发布
- 实现多项统计功能
- 支持年份选择
- 集成 Chart.js 图表展示
- 响应式布局设计

## 常见问题

### Q: 为什么浏览量显示为 0？

A: 浏览量统计需要安装额外的浏览统计插件（如 ViewsCounter），该插件会在文章表中添加 `views` 字段。如果没有安装，浏览量相关数据将显示为 0。

### Q: 统计数据不准确？

A: 请检查以下几点：
1. 确认文章的发布时间是否正确
2. 确认是否开启了「统计草稿」选项
3. 清除浏览器缓存后刷新页面

## 贡献

欢迎提交 Issue 和 Pull Request！

如果你有好的想法或发现了 Bug，请在 [GitHub Issues](https://github.com/xiangmingya/YearlySummary/issues) 中反馈。

## 开源协议

本项目基于 [MIT License](LICENSE) 开源。

## 作者

- **xiangmingya**
- 网站：[https://xiangming.site/](https://xiangming.site/)
- GitHub：[@xiangmingya](https://github.com/xiangmingya)

## 致谢

- [Typecho](https://typecho.org/) - 优秀的 PHP 博客程序
- [Chart.js](https://www.chartjs.org/) - 简洁的 JavaScript 图表库

---

如果这个插件对你有帮助，欢迎 Star 支持一下！
