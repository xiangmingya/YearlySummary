<?php
/**
 * YearlySummary 后台管理页面
 *
 * @package YearlySummary
 * @author xiangmingya
 * @version 1.1
 * @link https://xiangming.site/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

include 'common.php';
include 'header.php';
include 'menu.php';

// 加载统计类和更新类
require_once __DIR__ . '/../Stats.php';
require_once __DIR__ . '/../Update.php';

// 获取请求的年份
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$stats = new YearlySummary_Stats($currentYear);
$availableYears = $stats->getAvailableYears();

// 获取所有统计数据
$allStats = $stats->getAllStats();

// 获取插件配置
$config = YearlySummary_Plugin::getConfig();
$chartColor = isset($config->chartColor) ? $config->chartColor : '#667eea';

// 检查更新
$updateInfo = null;
if (!isset($config->checkUpdate) || $config->checkUpdate !== '0') {
    $updateInfo = YearlySummary_Update::check();
}
?>

<link rel="stylesheet" href="<?php $options->pluginUrl('YearlySummary/assets/css/style.css'); ?>">

<!-- 更新提示样式 -->
<style>
<?php echo YearlySummary_Update::getNoticeStyles(); ?>
</style>

<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>年度统计 - <?php echo $currentYear; ?>年</h2>
        </div>

        <!-- 更新提示 -->
        <?php if ($updateInfo && $updateInfo['has_update']): ?>
        <?php echo YearlySummary_Update::renderNotice($updateInfo); ?>
        <?php endif; ?>

        <!-- 工具栏 -->
        <div class="ys-toolbar">
            <div class="ys-toolbar-left">
                <label for="year-select">选择年份：</label>
                <select id="year-select" onchange="changeYear(this.value)">
                    <?php foreach ($availableYears as $year): ?>
                    <option value="<?php echo $year; ?>" <?php echo $year == $currentYear ? 'selected' : ''; ?>>
                        <?php echo $year; ?>年
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ys-toolbar-right">
                <button type="button" class="btn" onclick="exportData('json')">
                    <i class="i-download"></i> 导出 JSON
                </button>
                <button type="button" class="btn" onclick="exportData('csv')">
                    <i class="i-download"></i> 导出 CSV
                </button>
            </div>
        </div>

        <!-- 概览卡片 -->
        <div class="ys-section">
            <h3 class="ys-section-title">数据概览</h3>
            <div class="ys-cards">
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <span>📝</span>
                    </div>
                    <div class="ys-card-content">
                        <div class="ys-card-value"><?php echo number_format($allStats['overview']['totalPosts']); ?></div>
                        <div class="ys-card-label">文章总数</div>
                    </div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <span>✍️</span>
                    </div>
                    <div class="ys-card-content">
                        <div class="ys-card-value"><?php echo number_format($allStats['overview']['totalWords']); ?></div>
                        <div class="ys-card-label">总字数</div>
                    </div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <span>💬</span>
                    </div>
                    <div class="ys-card-content">
                        <div class="ys-card-value"><?php echo number_format($allStats['overview']['totalComments']); ?></div>
                        <div class="ys-card-label">总评论数</div>
                    </div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <span>👁️</span>
                    </div>
                    <div class="ys-card-content">
                        <div class="ys-card-value"><?php echo number_format($allStats['overview']['totalViews']); ?></div>
                        <div class="ys-card-label">总浏览量</div>
                    </div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <span>📊</span>
                    </div>
                    <div class="ys-card-content">
                        <div class="ys-card-value"><?php echo number_format($allStats['overview']['averageWords']); ?></div>
                        <div class="ys-card-label">平均字数/篇</div>
                    </div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                        <span>💭</span>
                    </div>
                    <div class="ys-card-content">
                        <div class="ys-card-value"><?php echo $allStats['overview']['averageComments']; ?></div>
                        <div class="ys-card-label">平均评论/篇</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 年度对比 -->
        <div class="ys-section">
            <h3 class="ys-section-title">年度对比 (<?php echo $allStats['comparison']['previous']['year']; ?> vs <?php echo $allStats['comparison']['current']['year']; ?>)</h3>
            <div class="ys-comparison">
                <table class="typecho-list-table">
                    <thead>
                        <tr>
                            <th>指标</th>
                            <th><?php echo $allStats['comparison']['previous']['year']; ?>年</th>
                            <th><?php echo $allStats['comparison']['current']['year']; ?>年</th>
                            <th>增长率</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>文章数</td>
                            <td><?php echo number_format($allStats['comparison']['previous']['posts']); ?></td>
                            <td><?php echo number_format($allStats['comparison']['current']['posts']); ?></td>
                            <td class="<?php echo $allStats['comparison']['growth']['posts'] >= 0 ? 'ys-growth-up' : 'ys-growth-down'; ?>">
                                <?php echo $allStats['comparison']['growth']['posts'] >= 0 ? '↑' : '↓'; ?>
                                <?php echo abs($allStats['comparison']['growth']['posts']); ?>%
                            </td>
                        </tr>
                        <tr>
                            <td>总字数</td>
                            <td><?php echo number_format($allStats['comparison']['previous']['words']); ?></td>
                            <td><?php echo number_format($allStats['comparison']['current']['words']); ?></td>
                            <td class="<?php echo $allStats['comparison']['growth']['words'] >= 0 ? 'ys-growth-up' : 'ys-growth-down'; ?>">
                                <?php echo $allStats['comparison']['growth']['words'] >= 0 ? '↑' : '↓'; ?>
                                <?php echo abs($allStats['comparison']['growth']['words']); ?>%
                            </td>
                        </tr>
                        <tr>
                            <td>评论数</td>
                            <td><?php echo number_format($allStats['comparison']['previous']['comments']); ?></td>
                            <td><?php echo number_format($allStats['comparison']['current']['comments']); ?></td>
                            <td class="<?php echo $allStats['comparison']['growth']['comments'] >= 0 ? 'ys-growth-up' : 'ys-growth-down'; ?>">
                                <?php echo $allStats['comparison']['growth']['comments'] >= 0 ? '↑' : '↓'; ?>
                                <?php echo abs($allStats['comparison']['growth']['comments']); ?>%
                            </td>
                        </tr>
                        <tr>
                            <td>浏览量</td>
                            <td><?php echo number_format($allStats['comparison']['previous']['views']); ?></td>
                            <td><?php echo number_format($allStats['comparison']['current']['views']); ?></td>
                            <td class="<?php echo $allStats['comparison']['growth']['views'] >= 0 ? 'ys-growth-up' : 'ys-growth-down'; ?>">
                                <?php echo $allStats['comparison']['growth']['views'] >= 0 ? '↑' : '↓'; ?>
                                <?php echo abs($allStats['comparison']['growth']['views']); ?>%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 图表区域 -->
        <div class="ys-section">
            <h3 class="ys-section-title">月度发布趋势</h3>
            <div class="ys-chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <div class="ys-row">
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">发布时段分布</h3>
                    <div class="ys-chart-container ys-chart-small">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">分类分布</h3>
                    <div class="ys-chart-container ys-chart-small">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 文章极值 -->
        <div class="ys-section">
            <h3 class="ys-section-title">文章字数极值</h3>
            <div class="ys-row">
                <div class="ys-col-6">
                    <div class="ys-highlight-card">
                        <div class="ys-highlight-label">最长文章</div>
                        <?php if ($allStats['content']['longestPost']): ?>
                        <div class="ys-highlight-title">
                            <a href="<?php $options->adminUrl('write-post.php?cid=' . $allStats['content']['longestPost']['cid']); ?>">
                                <?php echo htmlspecialchars($allStats['content']['longestPost']['title']); ?>
                            </a>
                        </div>
                        <div class="ys-highlight-meta">
                            <?php echo number_format($allStats['content']['longestPost']['words']); ?> 字
                            · <?php echo date('Y-m-d', $allStats['content']['longestPost']['created']); ?>
                        </div>
                        <?php else: ?>
                        <div class="ys-highlight-empty">暂无数据</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ys-col-6">
                    <div class="ys-highlight-card">
                        <div class="ys-highlight-label">最短文章</div>
                        <?php if ($allStats['content']['shortestPost']): ?>
                        <div class="ys-highlight-title">
                            <a href="<?php $options->adminUrl('write-post.php?cid=' . $allStats['content']['shortestPost']['cid']); ?>">
                                <?php echo htmlspecialchars($allStats['content']['shortestPost']['title']); ?>
                            </a>
                        </div>
                        <div class="ys-highlight-meta">
                            <?php echo number_format($allStats['content']['shortestPost']['words']); ?> 字
                            · <?php echo date('Y-m-d', $allStats['content']['shortestPost']['created']); ?>
                        </div>
                        <?php else: ?>
                        <div class="ys-highlight-empty">暂无数据</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 排行榜 -->
        <div class="ys-row">
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">浏览量排行 TOP10</h3>
                    <?php if (!empty($allStats['popularity']['topByViews'])): ?>
                    <table class="typecho-list-table">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>标题</th>
                                <th width="80">浏览量</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allStats['popularity']['topByViews'] as $index => $post): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="<?php $options->adminUrl('write-post.php?cid=' . $post['cid']); ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format($post['views']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="ys-empty">暂无浏览数据（需要安装浏览统计插件）</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">评论数排行 TOP10</h3>
                    <?php if (!empty($allStats['popularity']['topByComments'])): ?>
                    <table class="typecho-list-table">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>标题</th>
                                <th width="80">评论数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allStats['popularity']['topByComments'] as $index => $post): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="<?php $options->adminUrl('write-post.php?cid=' . $post['cid']); ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format($post['comments']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="ys-empty">暂无评论数据</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 活跃评论者 -->
        <div class="ys-section">
            <h3 class="ys-section-title">活跃评论者排行</h3>
            <?php if (!empty($allStats['popularity']['topCommenters'])): ?>
            <table class="typecho-list-table">
                <thead>
                    <tr>
                        <th width="40">#</th>
                        <th>昵称</th>
                        <th>邮箱</th>
                        <th>网站</th>
                        <th width="80">评论数</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allStats['popularity']['topCommenters'] as $index => $commenter): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($commenter['author']); ?></td>
                        <td><?php echo htmlspecialchars($commenter['mail']); ?></td>
                        <td>
                            <?php if ($commenter['url']): ?>
                            <a href="<?php echo htmlspecialchars($commenter['url']); ?>" target="_blank">
                                <?php echo htmlspecialchars($commenter['url']); ?>
                            </a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($commenter['count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="ys-empty">暂无评论者数据</div>
            <?php endif; ?>
        </div>

        <!-- 热门标签 -->
        <div class="ys-section">
            <h3 class="ys-section-title">热门标签 TOP10</h3>
            <?php if (!empty($allStats['content']['topTags'])): ?>
            <div class="ys-tags">
                <?php foreach ($allStats['content']['topTags'] as $tag): ?>
                <span class="ys-tag">
                    <?php echo htmlspecialchars($tag['name']); ?>
                    <em><?php echo $tag['count']; ?></em>
                </span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="ys-empty">暂无标签数据</div>
            <?php endif; ?>
        </div>

        <!-- 版权信息 -->
        <div class="ys-footer">
            <p>YearlySummary v<?php echo YearlySummary_Plugin::VERSION; ?> | 作者：<a href="https://xiangming.site/" target="_blank">xiangmingya</a></p>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
// 图表主题色
const chartColor = '<?php echo $chartColor; ?>';

// 月度数据
const monthlyData = <?php echo json_encode(array_values($allStats['timeline']['byMonth'])); ?>;
const monthlyLabels = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];

// 时段数据
const hourlyData = <?php echo json_encode(array_values($allStats['timeline']['byHour'])); ?>;
const hourlyLabels = <?php echo json_encode(array_keys($allStats['timeline']['byHour'])); ?>;

// 分类数据
const categoryData = <?php echo json_encode(array_column($allStats['content']['categories'], 'count')); ?>;
const categoryLabels = <?php echo json_encode(array_column($allStats['content']['categories'], 'name')); ?>;

// 月度趋势图
new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: '文章数',
            data: monthlyData,
            borderColor: chartColor,
            backgroundColor: chartColor + '20',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// 时段分布图
new Chart(document.getElementById('hourlyChart'), {
    type: 'doughnut',
    data: {
        labels: hourlyLabels,
        datasets: [{
            data: hourlyData,
            backgroundColor: [
                '#667eea',
                '#f093fb',
                '#4facfe',
                '#43e97b'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// 分类分布图
if (categoryData.length > 0) {
    new Chart(document.getElementById('categoryChart'), {
        type: 'pie',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryData,
                backgroundColor: [
                    '#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a',
                    '#fee140', '#a8edea', '#fed6e3', '#ff9a9e', '#fecfef'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// 切换年份
function changeYear(year) {
    window.location.href = '<?php $options->adminUrl('extending.php?panel=YearlySummary%2Fviews%2Fadmin.php'); ?>&year=' + year;
}

// 导出数据
function exportData(format) {
    const year = document.getElementById('year-select').value;
    window.location.href = '<?php $options->index('/yearly-summary/export'); ?>?year=' + year + '&format=' + format;
}
</script>

<?php include 'footer.php'; ?>
