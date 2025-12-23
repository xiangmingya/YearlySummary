<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$user = \Typecho\Widget::widget('Widget_User');
if (!$user->pass('administrator', true)) {
    die('无权限访问');
}

$options = \Widget\Options::alloc();

// 从数据库读取路由设置
$db = \Typecho\Db::get();
$settingQuery = $db->select()->from('table.options')->where('name = ?', 'routingTable');
$settingRow = $db->fetchRow($settingQuery);
$routingTable = @unserialize($settingRow['value']);
// post 路由的 key 可能是 'post' 或 'archives'
$postUrlRule = null;
if (isset($routingTable[0]['post']['url'])) {
    $postUrlRule = $routingTable[0]['post']['url'];
} elseif (isset($routingTable['post']['url'])) {
    $postUrlRule = $routingTable['post']['url'];
} elseif (isset($routingTable[0]['archives']['url'])) {
    $postUrlRule = $routingTable[0]['archives']['url'];
} elseif (isset($routingTable['archives']['url'])) {
    $postUrlRule = $routingTable['archives']['url'];
}
if (empty($postUrlRule)) {
    $postUrlRule = '/archives/{cid}/';
}
// 检测是否包含 .html 后缀（兼容 [cid:digital] 和 {cid} 两种格式）
if (strpos($postUrlRule, '.html') !== false) {
    $postUrlFormat = $options->siteUrl . '%s.html';
} else {
    $postUrlFormat = $options->siteUrl . '%s/';
}

try {
    $pluginConfig = $options->plugin('YearlySummary');
    $topLimit = isset($pluginConfig->topLimit) ? intval($pluginConfig->topLimit) : 10;
    $includeDraft = isset($pluginConfig->includeDraft) ? $pluginConfig->includeDraft : '0';
    $chartColor = isset($pluginConfig->chartColor) ? $pluginConfig->chartColor : '#667eea';
} catch (Exception $e) {
    $topLimit = 10;
    $includeDraft = '0';
    $chartColor = '#667eea';
}

$currentYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// 获取可用年份
$yearsQuery = $db->select('DISTINCT FROM_UNIXTIME(created, "%Y") as year')
    ->from('table.contents')
    ->where('type = ?', 'post')
    ->order('year', \Typecho\Db::SORT_DESC);
$yearsRows = $db->fetchAll($yearsQuery);
$availableYears = [];
foreach ($yearsRows as $row) {
    if (!empty($row['year'])) {
        $availableYears[] = $row['year'];
    }
}
if (empty($availableYears)) {
    $availableYears = [date('Y')];
}

// 时间范围
$startTime = mktime(0, 0, 0, 1, 1, $currentYear);
$endTime = mktime(23, 59, 59, 12, 31, $currentYear);
$statusCondition = ($includeDraft === '1') ? "status IN ('publish', 'draft')" : "status = 'publish'";

// 文章总数
$totalPostsQuery = $db->select('COUNT(*) as total')
    ->from('table.contents')
    ->where('type = ?', 'post')
    ->where('created >= ?', $startTime)
    ->where('created <= ?', $endTime)
    ->where($statusCondition);
$totalPosts = intval($db->fetchRow($totalPostsQuery)['total']);

// 评论总数
$commentsQuery = $db->select('COUNT(*) as total')
    ->from('table.comments')
    ->where('created >= ?', $startTime)
    ->where('created <= ?', $endTime)
    ->where('status = ?', 'approved');
$totalComments = intval($db->fetchRow($commentsQuery)['total']);

// 总字数（同时获取 cid, title, slug 供后续最长/最短文章使用）
$textsQuery = $db->select('cid, title, slug, text')
    ->from('table.contents')
    ->where('type = ?', 'post')
    ->where('created >= ?', $startTime)
    ->where('created <= ?', $endTime)
    ->where($statusCondition);
$textsRows = $db->fetchAll($textsQuery);
$totalWords = 0;
foreach ($textsRows as $row) {
    $text = strip_tags($row['text']);
    $text = preg_replace('/\s+/', '', $text);
    $totalWords += mb_strlen($text, 'UTF-8');
}
$averageWords = $totalPosts > 0 ? intval($totalWords / $totalPosts) : 0;
$averageComments = $totalPosts > 0 ? round($totalComments / $totalPosts, 2) : 0;

// 浏览量
$totalViews = 0;
try {
    $viewsQuery = $db->select('SUM(views) as total')
        ->from('table.contents')
        ->where('type = ?', 'post')
        ->where('created >= ?', $startTime)
        ->where('created <= ?', $endTime)
        ->where($statusCondition);
    $viewsResult = $db->fetchRow($viewsQuery);
    $totalViews = intval($viewsResult['total']);
} catch (Exception $e) {}

// 按月统计
$monthlyQuery = $db->select('FROM_UNIXTIME(created, "%m") as month, COUNT(*) as count')
    ->from('table.contents')
    ->where('type = ?', 'post')
    ->where('created >= ?', $startTime)
    ->where('created <= ?', $endTime)
    ->where($statusCondition)
    ->group('month')
    ->order('month', \Typecho\Db::SORT_ASC);
$monthlyRows = $db->fetchAll($monthlyQuery);
$monthlyData = [];
for ($i = 1; $i <= 12; $i++) {
    $monthlyData[str_pad($i, 2, '0', STR_PAD_LEFT)] = 0;
}
foreach ($monthlyRows as $row) {
    $monthlyData[$row['month']] = intval($row['count']);
}

// 按时段统计
$hourlyQuery = $db->select('FROM_UNIXTIME(created, "%H") as hour, COUNT(*) as count')
    ->from('table.contents')
    ->where('type = ?', 'post')
    ->where('created >= ?', $startTime)
    ->where('created <= ?', $endTime)
    ->where($statusCondition)
    ->group('hour');
$hourlyRows = $db->fetchAll($hourlyQuery);
$hourlyData = ['凌晨 (0-6点)' => 0, '上午 (6-12点)' => 0, '下午 (12-18点)' => 0, '晚上 (18-24点)' => 0];
foreach ($hourlyRows as $row) {
    $hour = intval($row['hour']);
    $count = intval($row['count']);
    if ($hour < 6) $hourlyData['凌晨 (0-6点)'] += $count;
    elseif ($hour < 12) $hourlyData['上午 (6-12点)'] += $count;
    elseif ($hour < 18) $hourlyData['下午 (12-18点)'] += $count;
    else $hourlyData['晚上 (18-24点)'] += $count;
}

// 评论排行
$commentRankQuery = $db->select('cid, title, commentsNum')
    ->from('table.contents')
    ->where('type = ?', 'post')
    ->where('created >= ?', $startTime)
    ->where('created <= ?', $endTime)
    ->where($statusCondition)
    ->order('commentsNum', \Typecho\Db::SORT_DESC)
    ->limit($topLimit);
$topByComments = $db->fetchAll($commentRankQuery);

// 浏览排行
$topByViews = [];
try {
    $viewRankQuery = $db->select('cid, title, views')
        ->from('table.contents')
        ->where('type = ?', 'post')
        ->where('created >= ?', $startTime)
        ->where('created <= ?', $endTime)
        ->where($statusCondition)
        ->order('views', \Typecho\Db::SORT_DESC)
        ->limit($topLimit);
    $topByViews = $db->fetchAll($viewRankQuery);
} catch (Exception $e) {}

// 活跃评论者
$commenterQuery = $db->select('author, mail, COUNT(*) as count')
    ->from('table.comments')
    ->where('created >= ?', $startTime)
    ->where('created <= ?', $endTime)
    ->where('status = ?', 'approved')
    ->group('mail')
    ->order('count', \Typecho\Db::SORT_DESC)
    ->limit($topLimit);
$topCommenters = $db->fetchAll($commenterQuery);

// 年度对比
$prevStartTime = mktime(0, 0, 0, 1, 1, $currentYear - 1);
$prevEndTime = mktime(23, 59, 59, 12, 31, $currentYear - 1);

$prevPostsQuery = $db->select('COUNT(*) as total')->from('table.contents')
    ->where('type = ?', 'post')->where('created >= ?', $prevStartTime)
    ->where('created <= ?', $prevEndTime)->where($statusCondition);
$prevPosts = intval($db->fetchRow($prevPostsQuery)['total']);

$prevCommentsQuery = $db->select('COUNT(*) as total')->from('table.comments')
    ->where('created >= ?', $prevStartTime)->where('created <= ?', $prevEndTime)
    ->where('status = ?', 'approved');
$prevComments = intval($db->fetchRow($prevCommentsQuery)['total']);

// 上一年浏览量
$prevViewsQuery = $db->select('SUM(views) as total')->from('table.contents')
    ->where('type = ?', 'post')->where('created >= ?', $prevStartTime)
    ->where('created <= ?', $prevEndTime)->where($statusCondition);
try {
    $prevViewsResult = $db->fetchRow($prevViewsQuery);
    $prevViews = intval($prevViewsResult['total']);
} catch (Exception $e) {
    $prevViews = 0;
}

// 上一年总字数
$prevTextsQuery = $db->select('text')->from('table.contents')
    ->where('type = ?', 'post')->where('created >= ?', $prevStartTime)
    ->where('created <= ?', $prevEndTime)->where($statusCondition);
$prevTextsRows = $db->fetchAll($prevTextsQuery);
$prevWords = 0;
foreach ($prevTextsRows as $row) {
    $text = strip_tags($row['text']);
    $text = preg_replace('/\s+/', '', $text);
    $prevWords += mb_strlen($text, 'UTF-8');
}

$calcGrowth = function($prev, $curr) {
    if ($prev == 0) return $curr > 0 ? 100 : 0;
    return round(($curr - $prev) / $prev * 100, 2);
};
$growthPosts = $calcGrowth($prevPosts, $totalPosts);
$growthComments = $calcGrowth($prevComments, $totalComments);
$growthViews = $calcGrowth($prevViews, $totalViews);
$growthWords = $calcGrowth($prevWords, $totalWords);

// 按周统计
$weeklyQuery = $db->select('WEEK(FROM_UNIXTIME(created), 1) as week, COUNT(*) as count')
    ->from('table.contents')
    ->where('type = ?', 'post')
    ->where('created >= ?', $startTime)
    ->where('created <= ?', $endTime)
    ->where($statusCondition)
    ->group('week')
    ->order('week', \Typecho\Db::SORT_ASC);
$weeklyRows = $db->fetchAll($weeklyQuery);
$weeklyData = [];
foreach ($weeklyRows as $row) {
    $weeklyData['第' . intval($row['week']) . '周'] = intval($row['count']);
}

// 最长文章
$longestPost = null;
$maxWords = 0;
foreach ($textsRows as $row) {
    $text = strip_tags($row['text']);
    $text = preg_replace('/\s+/', '', $text);
    $words = mb_strlen($text, 'UTF-8');
    if ($words > $maxWords) {
        $maxWords = $words;
        $longestPost = [
            'cid' => $row['cid'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'words' => $words
        ];
    }
}

// 最短文章
$shortestPost = null;
$minWords = PHP_INT_MAX;
foreach ($textsRows as $row) {
    $text = strip_tags($row['text']);
    $text = preg_replace('/\s+/', '', $text);
    $words = mb_strlen($text, 'UTF-8');
    if ($words > 0 && $words < $minWords) {
        $minWords = $words;
        $shortestPost = [
            'cid' => $row['cid'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'words' => $words
        ];
    }
}

// 先获取符合条件的文章CID列表
$cidsQuery = $db->select('cid')->from('table.contents')
    ->where('type = ?', 'post')
    ->where('created >= ?', $startTime)
    ->where('created <= ?', $endTime)
    ->where($statusCondition);
$cidsRows = $db->fetchAll($cidsQuery);
$cids = array_column($cidsRows, 'cid');
if (empty($cids)) {
    $cids = [0];
}

// 获取标签分布（用两步查询避免JOIN问题）
// 第一步：获取本年度文章的所有标签mid
$midQuery = $db->select(' DISTINCT mid')->from('table.relationships')
    ->where('cid IN ?', $cids);
$midRows = $db->fetchAll($midQuery);
$mids = array_column($midRows, 'mid');
if (empty($mids)) {
    $tagDistribution = [];
} else {
    // 第二步：获取每个标签的文章数，只保留type=tag的
    $tagDistribution = [];
    foreach ($mids as $mid) {
        $count = $db->fetchRow($db->select('COUNT(*) as count')->from('table.relationships')
            ->where('mid = ?', $mid)->where('cid IN ?', $cids));
        $meta = $db->fetchRow($db->select('name', 'slug')->from('table.metas')->where('mid = ?', $mid)->where('type = ?', 'tag'));
        if ($meta && $count['count'] > 0) {
            $tagDistribution[] = [
                'name' => $meta['name'],
                'slug' => $meta['slug'],
                'count' => intval($count['count'])
            ];
        }
    }
    // 按数量排序
    usort($tagDistribution, function($a, $b) { return $b['count'] - $a['count']; });
    $tagDistribution = array_slice($tagDistribution, 0, $topLimit);
}

// 分类分布（用两步查询避免JOIN问题）
// 第一步：获取本年度文章的所有分类mid
$catMidQuery = $db->select(' DISTINCT mid')->from('table.relationships')
    ->where('cid IN ?', $cids);
$catMidRows = $db->fetchAll($catMidQuery);
$catMids = array_column($catMidRows, 'mid');
if (empty($catMids)) {
    $categoryDistribution = [];
} else {
    // 第二步：获取每个分类的文章数，只保留type=category的
    $categoryDistribution = [];
    foreach ($catMids as $mid) {
        $count = $db->fetchRow($db->select('COUNT(*) as count')->from('table.relationships')
            ->where('mid = ?', $mid)->where('cid IN ?', $cids));
        $meta = $db->fetchRow($db->select('name', 'slug')->from('table.metas')->where('mid = ?', $mid)->where('type = ?', 'category'));
        if ($meta && $count['count'] > 0) {
            $categoryDistribution[] = [
                'name' => $meta['name'],
                'slug' => $meta['slug'],
                'count' => intval($count['count'])
            ];
        }
    }
    // 按数量排序
    usort($categoryDistribution, function($a, $b) { return $b['count'] - $a['count']; });
}
?>

<style>
.ys-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
.ys-toolbar-left { display: flex; align-items: center; gap: 10px; }
.ys-toolbar select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; }
.ys-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 20px; }
.ys-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; align-items: center; }
.ys-card-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 12px; color: #fff; }
.ys-card-value { font-size: 24px; font-weight: 700; color: #333; }
.ys-card-label { font-size: 12px; color: #888; margin-top: 2px; }
.ys-section { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.ys-section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
.ys-chart-container { position: relative; height: 280px; }
.ys-chart-small { height: 220px; }
.ys-row { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
.ys-col-6 { flex: 1; min-width: 300px; }
.ys-col-6 .ys-section { margin-bottom: 0; }
.ys-growth-up { color: #52c41a; font-weight: 600; }
.ys-growth-down { color: #ff4d4f; font-weight: 600; }
.ys-empty { padding: 30px; text-align: center; color: #999; }
.ys-footer { text-align: center; padding: 20px; color: #999; font-size: 12px; }
.ys-footer a { color: #667eea; text-decoration: none; }
.ys-link { color: #667eea; text-decoration: none; }
.ys-link:hover { text-decoration: underline; }
@media (max-width: 768px) { .ys-row { flex-direction: column; } .ys-cards { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>年度统计 - <?php echo $currentYear; ?>年</h2>
        </div>

        <div class="ys-toolbar">
            <div class="ys-toolbar-left">
                <label>选择年份：</label>
                <select id="year-select" onchange="location.href='<?php echo $options->adminUrl; ?>extending.php?panel=YearlySummary%2FPanel.php&year='+this.value">
                    <?php foreach ($availableYears as $year): ?>
                    <option value="<?php echo $year; ?>" <?php echo $year == $currentYear ? 'selected' : ''; ?>><?php echo $year; ?>年</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="ys-section">
            <h3 class="ys-section-title">数据概览</h3>
            <div class="ys-cards">
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">📝</div>
                    <div><div class="ys-card-value"><?php echo number_format($totalPosts); ?></div><div class="ys-card-label">文章总数</div></div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">✍️</div>
                    <div><div class="ys-card-value"><?php echo number_format($totalWords); ?></div><div class="ys-card-label">总字数</div></div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">💬</div>
                    <div><div class="ys-card-value"><?php echo number_format($totalComments); ?></div><div class="ys-card-label">总评论数</div></div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">👁️</div>
                    <div><div class="ys-card-value"><?php echo number_format($totalViews); ?></div><div class="ys-card-label">总浏览量</div></div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">📊</div>
                    <div><div class="ys-card-value"><?php echo number_format($averageWords); ?></div><div class="ys-card-label">平均字数/篇</div></div>
                </div>
                <div class="ys-card">
                    <div class="ys-card-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">💭</div>
                    <div><div class="ys-card-value"><?php echo $averageComments; ?></div><div class="ys-card-label">平均评论/篇</div></div>
                </div>
            </div>
        </div>

        <div class="ys-section">
            <h3 class="ys-section-title">年度对比 (<?php echo $currentYear - 1; ?> vs <?php echo $currentYear; ?>)</h3>
            <table class="typecho-list-table">
                <thead><tr><th>指标</th><th><?php echo $currentYear - 1; ?>年</th><th><?php echo $currentYear; ?>年</th><th>增长率</th></tr></thead>
                <tbody>
                    <tr><td>文章数</td><td><?php echo $prevPosts; ?></td><td><?php echo $totalPosts; ?></td><td class="<?php echo $growthPosts >= 0 ? 'ys-growth-up' : 'ys-growth-down'; ?>"><?php echo $growthPosts >= 0 ? '↑' : '↓'; ?><?php echo abs($growthPosts); ?>%</td></tr>
                    <tr><td>评论数</td><td><?php echo $prevComments; ?></td><td><?php echo $totalComments; ?></td><td class="<?php echo $growthComments >= 0 ? 'ys-growth-up' : 'ys-growth-down'; ?>"><?php echo $growthComments >= 0 ? '↑' : '↓'; ?><?php echo abs($growthComments); ?>%</td></tr>
                    <tr><td>总字数</td><td><?php echo number_format($prevWords); ?></td><td><?php echo number_format($totalWords); ?></td><td class="<?php echo $growthWords >= 0 ? 'ys-growth-up' : 'ys-growth-down'; ?>"><?php echo $growthWords >= 0 ? '↑' : '↓'; ?><?php echo abs($growthWords); ?>%</td></tr>
                    <tr><td>总浏览量</td><td><?php echo number_format($prevViews); ?></td><td><?php echo number_format($totalViews); ?></td><td class="<?php echo $growthViews >= 0 ? 'ys-growth-up' : 'ys-growth-down'; ?>"><?php echo $growthViews >= 0 ? '↑' : '↓'; ?><?php echo abs($growthViews); ?>%</td></tr>
                </tbody>
            </table>
        </div>

        <div class="ys-row">
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">最长/最短文章</h3>
                    <?php if ($longestPost || $shortestPost): ?>
                    <div style="display: flex; gap: 15px;">
                        <?php if ($longestPost): ?>
                        <div style="flex:1; padding: 15px; background: #f0f9ff; border-radius: 8px;">
                            <div style="font-size: 12px; color: #1890ff; margin-bottom: 5px;">最长文章</div>
                            <a href="<?php echo sprintf($postUrlFormat, $longestPost['cid']); ?>" class="ys-link" style="font-weight: 600; margin-bottom: 5px; display:block;" target="_blank"><?php echo htmlspecialchars($longestPost['title']); ?></a>
                            <div style="font-size: 13px; color: #666;"><?php echo number_format($longestPost['words']); ?> 字</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($shortestPost): ?>
                        <div style="flex:1; padding: 15px; background: #fff7e6; border-radius: 8px;">
                            <div style="font-size: 12px; color: #fa8c16; margin-bottom: 5px;">最短文章</div>
                            <a href="<?php echo sprintf($postUrlFormat, $shortestPost['cid']); ?>" class="ys-link" style="font-weight: 600; margin-bottom: 5px; display:block;" target="_blank"><?php echo htmlspecialchars($shortestPost['title']); ?></a>
                            <div style="font-size: 13px; color: #666;"><?php echo number_format($shortestPost['words']); ?> 字</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?><div class="ys-empty">暂无数据</div><?php endif; ?>
                </div>
            </div>
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">按周发布统计</h3>
                    <div class="ys-chart-container ys-chart-small"><canvas id="weeklyChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="ys-section">
            <h3 class="ys-section-title">月度发布趋势</h3>
            <div class="ys-chart-container"><canvas id="monthlyChart"></canvas></div>
        </div>

        <div class="ys-row">
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">发布时段分布</h3>
                    <div class="ys-chart-container ys-chart-small"><canvas id="hourlyChart"></canvas></div>
                </div>
            </div>
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">分类分布</h3>
                    <?php if (!empty($categoryDistribution)): ?>
                    <div class="ys-chart-container ys-chart-small"><canvas id="categoryChart"></canvas></div>
                    <?php else: ?><div class="ys-empty">暂无分类</div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ys-row">
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">标签分布 TOP<?php echo $topLimit; ?></h3>
                    <?php if (!empty($tagDistribution)): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($tagDistribution as $tag): ?>
                        <span style="padding: 5px 12px; background: #f0f0f0; border-radius: 15px; font-size: 13px;">
                            <?php echo htmlspecialchars($tag['name']); ?> (<?php echo $tag['count']; ?>)
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><div class="ys-empty">暂无标签</div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ys-row">
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">浏览量排行 TOP<?php echo $topLimit; ?></h3>
                    <?php if (!empty($topByViews)): ?>
                    <table class="typecho-list-table">
                        <thead><tr><th>#</th><th>标题</th><th>浏览量</th></tr></thead>
                        <tbody>
                        <?php foreach ($topByViews as $i => $post): ?>
                        <tr><td><?php echo $i + 1; ?></td><td><a href="<?php echo sprintf($postUrlFormat, $post['cid']); ?>" class="ys-link" target="_blank"><?php echo htmlspecialchars($post['title']); ?></a></td><td><?php echo number_format($post['views'] ?? 0); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?><div class="ys-empty">暂无数据</div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ys-row">
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">评论数排行 TOP<?php echo $topLimit; ?></h3>
                    <?php if (!empty($topByComments)): ?>
                    <table class="typecho-list-table">
                        <thead><tr><th>#</th><th>标题</th><th>评论数</th></tr></thead>
                        <tbody>
                        <?php foreach ($topByComments as $i => $post): ?>
                        <tr><td><?php echo $i + 1; ?></td><td><a href="<?php echo sprintf($postUrlFormat, $post['cid']); ?>" class="ys-link" target="_blank"><?php echo htmlspecialchars($post['title']); ?></a></td><td><?php echo number_format($post['commentsNum']); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?><div class="ys-empty">暂无数据</div><?php endif; ?>
                </div>
            </div>
            <div class="ys-col-6">
                <div class="ys-section">
                    <h3 class="ys-section-title">活跃评论者排行</h3>
                    <?php if (!empty($topCommenters)): ?>
                    <table class="typecho-list-table">
                        <thead><tr><th>#</th><th>昵称</th><th>评论数</th></tr></thead>
                        <tbody>
                        <?php foreach ($topCommenters as $i => $c): ?>
                        <tr><td><?php echo $i + 1; ?></td><td><?php echo htmlspecialchars($c['author']); ?></td><td><?php echo $c['count']; ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?><div class="ys-empty">暂无数据</div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ys-footer">
            <p>YearlySummary v1.1.0 | 作者：<a href="https://xiangming.site/" target="_blank">xiangmingya</a></p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const chartColor = '<?php echo $chartColor; ?>';
const monthlyData = <?php echo json_encode(array_values($monthlyData)); ?>;
const hourlyData = <?php echo json_encode(array_values($hourlyData)); ?>;
const hourlyLabels = <?php echo json_encode(array_keys($hourlyData)); ?>;
const weeklyData = <?php echo json_encode(array_values($weeklyData)); ?>;
const weeklyLabels = <?php echo json_encode(array_keys($weeklyData)); ?>;
const categoryLabels = <?php echo json_encode(array_column($categoryDistribution, 'name')); ?>;
const categoryData = <?php echo json_encode(array_column($categoryDistribution, 'count')); ?>;
const categoryColors = ['#667eea','#f093fb','#4facfe','#43e97b','#fa709a','#fee140','#a8edea','#fed6e3'];

new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: { labels: ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'], datasets: [{ label: '文章数', data: monthlyData, borderColor: chartColor, backgroundColor: chartColor + '20', fill: true, tension: 0.4 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('hourlyChart'), {
    type: 'doughnut',
    data: { labels: hourlyLabels, datasets: [{ data: hourlyData, backgroundColor: ['#667eea','#f093fb','#4facfe','#43e97b'] }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});

<?php if (!empty($weeklyData)): ?>
new Chart(document.getElementById('weeklyChart'), {
    type: 'bar',
    data: { labels: weeklyLabels, datasets: [{ label: '文章数', data: weeklyData, backgroundColor: chartColor + '80', borderColor: chartColor, borderWidth: 1 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
<?php endif; ?>

<?php if (!empty($categoryDistribution)): ?>
new Chart(document.getElementById('categoryChart'), {
    type: 'pie',
    data: { labels: categoryLabels, datasets: [{ data: categoryData, backgroundColor: categoryColors }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});
<?php endif; ?>
</script>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>
