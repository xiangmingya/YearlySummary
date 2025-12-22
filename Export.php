<?php
/**
 * YearlySummary 导出功能类
 *
 * @package YearlySummary
 * @author xiangmingya
 * @version 1.0
 * @link https://xiangming.site/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class YearlySummary_Export
{
    /**
     * 统计数据
     */
    private $data;

    /**
     * 年份
     */
    private $year;

    /**
     * 构造函数
     *
     * @param array $data 统计数据
     * @param int $year 年份
     */
    public function __construct($data, $year)
    {
        $this->data = $data;
        $this->year = $year;
    }

    /**
     * 导出为JSON格式
     */
    public function toJSON()
    {
        $response = Typecho_Response::getInstance();
        $response->setContentType('application/json');
        header('Content-Disposition: attachment; filename="yearly_summary_' . $this->year . '.json"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        $exportData = array(
            'meta' => array(
                'plugin' => 'YearlySummary',
                'version' => '1.0',
                'author' => 'xiangmingya',
                'website' => 'https://xiangming.site/',
                'exportTime' => date('Y-m-d H:i:s'),
                'year' => $this->year
            ),
            'data' => $this->data
        );

        echo json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 导出为CSV格式
     */
    public function toCSV()
    {
        $response = Typecho_Response::getInstance();
        $response->setContentType('text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="yearly_summary_' . $this->year . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        // 添加BOM以支持Excel正确显示中文
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // 元信息
        $this->writeCsvSection($output, '导出信息', array(
            array('插件名称', 'YearlySummary'),
            array('版本', '1.0'),
            array('作者', 'xiangmingya'),
            array('网站', 'https://xiangming.site/'),
            array('导出时间', date('Y-m-d H:i:s')),
            array('统计年份', $this->year)
        ));

        // 概览数据
        $this->writeCsvSection($output, '数据概览', array(
            array('指标', '数值'),
            array('文章总数', $this->data['overview']['totalPosts']),
            array('总字数', $this->data['overview']['totalWords']),
            array('平均字数/篇', $this->data['overview']['averageWords']),
            array('总评论数', $this->data['overview']['totalComments']),
            array('平均评论/篇', $this->data['overview']['averageComments']),
            array('总浏览量', $this->data['overview']['totalViews'])
        ));

        // 月度统计
        $monthlyRows = array(array('月份', '文章数'));
        foreach ($this->data['timeline']['byMonth'] as $month => $count) {
            $monthlyRows[] = array($month . '月', $count);
        }
        $this->writeCsvSection($output, '月度文章统计', $monthlyRows);

        // 周度统计
        $weeklyRows = array(array('周次', '文章数'));
        foreach ($this->data['timeline']['byWeek'] as $week => $count) {
            $weeklyRows[] = array($week, $count);
        }
        $this->writeCsvSection($output, '周度文章统计', $weeklyRows);

        // 时段分布
        $hourlyRows = array(array('时段', '文章数'));
        foreach ($this->data['timeline']['byHour'] as $period => $count) {
            $hourlyRows[] = array($period, $count);
        }
        $this->writeCsvSection($output, '发布时段分布', $hourlyRows);

        // 分类分布
        $categoryRows = array(array('分类', '文章数'));
        foreach ($this->data['content']['categories'] as $category) {
            $categoryRows[] = array($category['name'], $category['count']);
        }
        $this->writeCsvSection($output, '分类分布', $categoryRows);

        // 标签分布
        $tagRows = array(array('标签', '使用次数'));
        foreach ($this->data['content']['topTags'] as $tag) {
            $tagRows[] = array($tag['name'], $tag['count']);
        }
        $this->writeCsvSection($output, '热门标签 TOP10', $tagRows);

        // 最长/最短文章
        $extremeRows = array(array('类型', '标题', '字数', '发布日期'));
        if ($this->data['content']['longestPost']) {
            $extremeRows[] = array(
                '最长文章',
                $this->data['content']['longestPost']['title'],
                $this->data['content']['longestPost']['words'],
                date('Y-m-d', $this->data['content']['longestPost']['created'])
            );
        }
        if ($this->data['content']['shortestPost']) {
            $extremeRows[] = array(
                '最短文章',
                $this->data['content']['shortestPost']['title'],
                $this->data['content']['shortestPost']['words'],
                date('Y-m-d', $this->data['content']['shortestPost']['created'])
            );
        }
        $this->writeCsvSection($output, '文章字数极值', $extremeRows);

        // 浏览量排行
        $viewRows = array(array('排名', '标题', '浏览量', '发布日期'));
        $rank = 1;
        foreach ($this->data['popularity']['topByViews'] as $post) {
            $viewRows[] = array(
                $rank++,
                $post['title'],
                $post['views'],
                date('Y-m-d', $post['created'])
            );
        }
        $this->writeCsvSection($output, '浏览量排行', $viewRows);

        // 评论数排行
        $commentRows = array(array('排名', '标题', '评论数', '发布日期'));
        $rank = 1;
        foreach ($this->data['popularity']['topByComments'] as $post) {
            $commentRows[] = array(
                $rank++,
                $post['title'],
                $post['comments'],
                date('Y-m-d', $post['created'])
            );
        }
        $this->writeCsvSection($output, '评论数排行', $commentRows);

        // 活跃评论者
        $commenterRows = array(array('排名', '昵称', '邮箱', '网站', '评论数'));
        $rank = 1;
        foreach ($this->data['popularity']['topCommenters'] as $commenter) {
            $commenterRows[] = array(
                $rank++,
                $commenter['author'],
                $commenter['mail'],
                $commenter['url'] ?: '-',
                $commenter['count']
            );
        }
        $this->writeCsvSection($output, '活跃评论者排行', $commenterRows);

        // 年度对比
        $comparisonRows = array(
            array('指标', $this->data['comparison']['previous']['year'] . '年', $this->data['comparison']['current']['year'] . '年', '增长率'),
            array('文章数', $this->data['comparison']['previous']['posts'], $this->data['comparison']['current']['posts'], $this->data['comparison']['growth']['posts'] . '%'),
            array('总字数', $this->data['comparison']['previous']['words'], $this->data['comparison']['current']['words'], $this->data['comparison']['growth']['words'] . '%'),
            array('评论数', $this->data['comparison']['previous']['comments'], $this->data['comparison']['current']['comments'], $this->data['comparison']['growth']['comments'] . '%'),
            array('浏览量', $this->data['comparison']['previous']['views'], $this->data['comparison']['current']['views'], $this->data['comparison']['growth']['views'] . '%')
        );
        $this->writeCsvSection($output, '年度对比', $comparisonRows);

        fclose($output);
    }

    /**
     * 导出为HTML格式（可用于打印）
     */
    public function toHTML()
    {
        $response = Typecho_Response::getInstance();
        $response->setContentType('text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="yearly_summary_' . $this->year . '.html"');

        $html = $this->generateHTMLReport();
        echo $html;
    }

    /**
     * 生成HTML报告
     */
    private function generateHTMLReport()
    {
        $html = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $this->year . '年度统计报告</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #667eea; margin-bottom: 10px; }
        .subtitle { text-align: center; color: #666; margin-bottom: 30px; }
        h2 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; margin: 30px 0 20px; }
        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 8px; text-align: center; }
        .card-value { font-size: 32px; font-weight: bold; }
        .card-label { font-size: 14px; opacity: 0.9; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .growth-up { color: #52c41a; }
        .growth-down { color: #ff4d4f; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 14px; }
        .footer a { color: #667eea; text-decoration: none; }
        @media print { body { background: #fff; } .container { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>' . $this->year . '年度统计报告</h1>
        <p class="subtitle">生成时间：' . date('Y-m-d H:i:s') . '</p>

        <h2>数据概览</h2>
        <div class="cards">
            <div class="card">
                <div class="card-value">' . number_format($this->data['overview']['totalPosts']) . '</div>
                <div class="card-label">文章总数</div>
            </div>
            <div class="card">
                <div class="card-value">' . number_format($this->data['overview']['totalWords']) . '</div>
                <div class="card-label">总字数</div>
            </div>
            <div class="card">
                <div class="card-value">' . number_format($this->data['overview']['totalComments']) . '</div>
                <div class="card-label">总评论数</div>
            </div>
            <div class="card">
                <div class="card-value">' . number_format($this->data['overview']['totalViews']) . '</div>
                <div class="card-label">总浏览量</div>
            </div>
            <div class="card">
                <div class="card-value">' . number_format($this->data['overview']['averageWords']) . '</div>
                <div class="card-label">平均字数/篇</div>
            </div>
            <div class="card">
                <div class="card-value">' . $this->data['overview']['averageComments'] . '</div>
                <div class="card-label">平均评论/篇</div>
            </div>
        </div>

        <h2>月度文章统计</h2>
        <table>
            <tr><th>月份</th><th>文章数</th></tr>';

        foreach ($this->data['timeline']['byMonth'] as $month => $count) {
            $html .= '<tr><td>' . $month . '月</td><td>' . $count . '</td></tr>';
        }

        $html .= '</table>

        <h2>年度对比</h2>
        <table>
            <tr>
                <th>指标</th>
                <th>' . $this->data['comparison']['previous']['year'] . '年</th>
                <th>' . $this->data['comparison']['current']['year'] . '年</th>
                <th>增长率</th>
            </tr>
            <tr>
                <td>文章数</td>
                <td>' . number_format($this->data['comparison']['previous']['posts']) . '</td>
                <td>' . number_format($this->data['comparison']['current']['posts']) . '</td>
                <td class="' . ($this->data['comparison']['growth']['posts'] >= 0 ? 'growth-up' : 'growth-down') . '">' . ($this->data['comparison']['growth']['posts'] >= 0 ? '↑' : '↓') . abs($this->data['comparison']['growth']['posts']) . '%</td>
            </tr>
            <tr>
                <td>总字数</td>
                <td>' . number_format($this->data['comparison']['previous']['words']) . '</td>
                <td>' . number_format($this->data['comparison']['current']['words']) . '</td>
                <td class="' . ($this->data['comparison']['growth']['words'] >= 0 ? 'growth-up' : 'growth-down') . '">' . ($this->data['comparison']['growth']['words'] >= 0 ? '↑' : '↓') . abs($this->data['comparison']['growth']['words']) . '%</td>
            </tr>
            <tr>
                <td>评论数</td>
                <td>' . number_format($this->data['comparison']['previous']['comments']) . '</td>
                <td>' . number_format($this->data['comparison']['current']['comments']) . '</td>
                <td class="' . ($this->data['comparison']['growth']['comments'] >= 0 ? 'growth-up' : 'growth-down') . '">' . ($this->data['comparison']['growth']['comments'] >= 0 ? '↑' : '↓') . abs($this->data['comparison']['growth']['comments']) . '%</td>
            </tr>
            <tr>
                <td>浏览量</td>
                <td>' . number_format($this->data['comparison']['previous']['views']) . '</td>
                <td>' . number_format($this->data['comparison']['current']['views']) . '</td>
                <td class="' . ($this->data['comparison']['growth']['views'] >= 0 ? 'growth-up' : 'growth-down') . '">' . ($this->data['comparison']['growth']['views'] >= 0 ? '↑' : '↓') . abs($this->data['comparison']['growth']['views']) . '%</td>
            </tr>
        </table>

        <div class="footer">
            <p>由 <a href="https://xiangming.site/">YearlySummary</a> 插件生成 | 作者：xiangmingya</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * 写入CSV区块
     */
    private function writeCsvSection($output, $title, $rows)
    {
        fputcsv($output, array(''));
        fputcsv($output, array('=== ' . $title . ' ==='));
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
    }
}
