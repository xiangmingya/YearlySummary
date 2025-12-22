<?php
/**
 * YearlySummary 后台控制器
 *
 * @package YearlySummary
 * @author xiangmingya
 * @version 1.0
 * @link https://xiangming.site/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class YearlySummary_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * 统计实例
     */
    private $stats;

    /**
     * 执行入口
     */
    public function execute()
    {
        // 验证用户权限
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->pass('administrator', true)) {
            throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
        }
    }

    /**
     * API接口 - 获取统计数据
     */
    public function api()
    {
        $this->execute();

        $request = Typecho_Request::getInstance();
        $year = $request->get('year', date('Y'));
        $type = $request->get('type', 'all');

        $this->stats = new YearlySummary_Stats($year);

        $response = Typecho_Response::getInstance();
        $response->setContentType('application/json');

        $data = array();

        switch ($type) {
            case 'overview':
                $data = $this->getOverviewData();
                break;
            case 'timeline':
                $data = $this->getTimelineData();
                break;
            case 'content':
                $data = $this->getContentData();
                break;
            case 'popularity':
                $data = $this->getPopularityData();
                break;
            case 'comparison':
                $data = $this->stats->getYearComparison();
                break;
            case 'all':
            default:
                $data = $this->stats->getAllStats();
                break;
        }

        echo json_encode(array(
            'success' => true,
            'data' => $data
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 导出数据
     */
    public function export()
    {
        $this->execute();

        $request = Typecho_Request::getInstance();
        $year = $request->get('year', date('Y'));
        $format = $request->get('format', 'json');

        $this->stats = new YearlySummary_Stats($year);
        $data = $this->stats->getAllStats();

        $response = Typecho_Response::getInstance();

        switch ($format) {
            case 'csv':
                $this->exportCSV($data, $year);
                break;
            case 'json':
            default:
                $this->exportJSON($data, $year);
                break;
        }

        exit;
    }

    /**
     * 导出为JSON格式
     */
    private function exportJSON($data, $year)
    {
        $response = Typecho_Response::getInstance();
        $response->setContentType('application/json');
        header('Content-Disposition: attachment; filename="yearly_summary_' . $year . '.json"');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 导出为CSV格式
     */
    private function exportCSV($data, $year)
    {
        $response = Typecho_Response::getInstance();
        $response->setContentType('text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="yearly_summary_' . $year . '.csv"');

        // 添加BOM以支持Excel正确显示中文
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // 概览数据
        fputcsv($output, array('=== ' . $year . '年度统计概览 ==='));
        fputcsv($output, array(''));
        fputcsv($output, array('指标', '数值'));
        fputcsv($output, array('文章总数', $data['overview']['totalPosts']));
        fputcsv($output, array('总字数', $data['overview']['totalWords']));
        fputcsv($output, array('平均字数', $data['overview']['averageWords']));
        fputcsv($output, array('总评论数', $data['overview']['totalComments']));
        fputcsv($output, array('平均评论数', $data['overview']['averageComments']));
        fputcsv($output, array('总浏览量', $data['overview']['totalViews']));

        // 月度统计
        fputcsv($output, array(''));
        fputcsv($output, array('=== 月度文章统计 ==='));
        fputcsv($output, array('月份', '文章数'));
        foreach ($data['timeline']['byMonth'] as $month => $count) {
            fputcsv($output, array($month . '月', $count));
        }

        // 时段分布
        fputcsv($output, array(''));
        fputcsv($output, array('=== 发布时段分布 ==='));
        fputcsv($output, array('时段', '文章数'));
        foreach ($data['timeline']['byHour'] as $period => $count) {
            fputcsv($output, array($period, $count));
        }

        // 分类分布
        fputcsv($output, array(''));
        fputcsv($output, array('=== 分类分布 ==='));
        fputcsv($output, array('分类', '文章数'));
        foreach ($data['content']['categories'] as $category) {
            fputcsv($output, array($category['name'], $category['count']));
        }

        // 标签分布
        fputcsv($output, array(''));
        fputcsv($output, array('=== 热门标签 ==='));
        fputcsv($output, array('标签', '使用次数'));
        foreach ($data['content']['topTags'] as $tag) {
            fputcsv($output, array($tag['name'], $tag['count']));
        }

        // 浏览量排行
        fputcsv($output, array(''));
        fputcsv($output, array('=== 浏览量排行 ==='));
        fputcsv($output, array('排名', '标题', '浏览量'));
        $rank = 1;
        foreach ($data['popularity']['topByViews'] as $post) {
            fputcsv($output, array($rank++, $post['title'], $post['views']));
        }

        // 评论数排行
        fputcsv($output, array(''));
        fputcsv($output, array('=== 评论数排行 ==='));
        fputcsv($output, array('排名', '标题', '评论数'));
        $rank = 1;
        foreach ($data['popularity']['topByComments'] as $post) {
            fputcsv($output, array($rank++, $post['title'], $post['comments']));
        }

        // 活跃评论者
        fputcsv($output, array(''));
        fputcsv($output, array('=== 活跃评论者 ==='));
        fputcsv($output, array('排名', '昵称', '评论数'));
        $rank = 1;
        foreach ($data['popularity']['topCommenters'] as $commenter) {
            fputcsv($output, array($rank++, $commenter['author'], $commenter['count']));
        }

        // 年度对比
        fputcsv($output, array(''));
        fputcsv($output, array('=== 年度对比 ==='));
        fputcsv($output, array('指标', $data['comparison']['previous']['year'] . '年', $data['comparison']['current']['year'] . '年', '增长率'));
        fputcsv($output, array('文章数', $data['comparison']['previous']['posts'], $data['comparison']['current']['posts'], $data['comparison']['growth']['posts'] . '%'));
        fputcsv($output, array('字数', $data['comparison']['previous']['words'], $data['comparison']['current']['words'], $data['comparison']['growth']['words'] . '%'));
        fputcsv($output, array('评论数', $data['comparison']['previous']['comments'], $data['comparison']['current']['comments'], $data['comparison']['growth']['comments'] . '%'));
        fputcsv($output, array('浏览量', $data['comparison']['previous']['views'], $data['comparison']['current']['views'], $data['comparison']['growth']['views'] . '%'));

        fclose($output);
    }

    /**
     * 获取概览数据
     */
    private function getOverviewData()
    {
        return array(
            'totalPosts' => $this->stats->getTotalPosts(),
            'totalWords' => $this->stats->getTotalWords(),
            'averageWords' => $this->stats->getAverageWords(),
            'totalComments' => $this->stats->getTotalComments(),
            'averageComments' => $this->stats->getAverageComments(),
            'totalViews' => $this->stats->getTotalViews()
        );
    }

    /**
     * 获取时间线数据
     */
    private function getTimelineData()
    {
        return array(
            'byMonth' => $this->stats->getPostsByMonth(),
            'byWeek' => $this->stats->getPostsByWeek(),
            'byHour' => $this->stats->getPostsByHour()
        );
    }

    /**
     * 获取内容分析数据
     */
    private function getContentData()
    {
        return array(
            'longestPost' => $this->stats->getLongestPost(),
            'shortestPost' => $this->stats->getShortestPost(),
            'categories' => $this->stats->getCategoryDistribution(),
            'tags' => $this->stats->getTagDistribution(),
            'topTags' => $this->stats->getTopTags()
        );
    }

    /**
     * 获取热度数据
     */
    private function getPopularityData()
    {
        return array(
            'topByViews' => $this->stats->getTopPostsByViews(),
            'topByComments' => $this->stats->getTopPostsByComments(),
            'topCommenters' => $this->stats->getTopCommenters()
        );
    }

    /**
     * 实现接口方法
     */
    public function action()
    {
        $this->on($this->request->is('do=api'))->api();
        $this->on($this->request->is('do=export'))->export();
    }
}
