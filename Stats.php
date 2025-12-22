<?php
/**
 * YearlySummary 统计核心类
 *
 * @package YearlySummary
 * @author xiangmingya
 * @version 1.0
 * @link https://xiangming.site/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class YearlySummary_Stats
{
    /**
     * 数据库实例
     */
    private $db;

    /**
     * 表前缀
     */
    private $prefix;

    /**
     * 统计年份
     */
    private $year;

    /**
     * 年份开始时间戳
     */
    private $startTime;

    /**
     * 年份结束时间戳
     */
    private $endTime;

    /**
     * 是否包含草稿
     */
    private $includeDraft;

    /**
     * 排行榜条数限制
     */
    private $topLimit;

    /**
     * 构造函数
     *
     * @param int $year 统计年份
     */
    public function __construct($year = null)
    {
        $this->db = Typecho_Db::get();
        $this->prefix = $this->db->getPrefix();

        // 获取插件配置
        $config = YearlySummary_Plugin::getConfig();
        $this->topLimit = isset($config->topLimit) ? intval($config->topLimit) : 10;
        $this->includeDraft = isset($config->includeDraft) ? $config->includeDraft : '0';

        // 设置年份
        $this->setYear($year ?: (isset($config->defaultYear) ? $config->defaultYear : date('Y')));
    }

    /**
     * 设置统计年份
     *
     * @param int $year 年份
     */
    public function setYear($year)
    {
        $this->year = intval($year);
        $this->startTime = mktime(0, 0, 0, 1, 1, $this->year);
        $this->endTime = mktime(23, 59, 59, 12, 31, $this->year);
    }

    /**
     * 获取当前统计年份
     *
     * @return int
     */
    public function getYear()
    {
        return $this->year;
    }

    /**
     * 获取可用年份列表
     *
     * @return array
     */
    public function getAvailableYears()
    {
        $query = $this->db->select('DISTINCT FROM_UNIXTIME(created, "%Y") as year')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->order('year', Typecho_Db::SORT_DESC);

        $rows = $this->db->fetchAll($query);
        $years = array();

        foreach ($rows as $row) {
            if (!empty($row['year'])) {
                $years[] = $row['year'];
            }
        }

        return $years;
    }

    /**
     * 获取文章状态条件
     *
     * @return string
     */
    private function getStatusCondition()
    {
        if ($this->includeDraft === '1') {
            return "status IN ('publish', 'draft')";
        }
        return "status = 'publish'";
    }

    /**
     * 获取文章总数
     *
     * @return int
     */
    public function getTotalPosts()
    {
        $query = $this->db->select('COUNT(*) as total')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('created >= ?', $this->startTime)
            ->where('created <= ?', $this->endTime)
            ->where($this->getStatusCondition());

        $result = $this->db->fetchRow($query);
        return intval($result['total']);
    }

    /**
     * 按月统计文章数量
     *
     * @return array
     */
    public function getPostsByMonth()
    {
        $query = $this->db->select('FROM_UNIXTIME(created, "%m") as month, COUNT(*) as count')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('created >= ?', $this->startTime)
            ->where('created <= ?', $this->endTime)
            ->where($this->getStatusCondition())
            ->group('month')
            ->order('month', Typecho_Db::SORT_ASC);

        $rows = $this->db->fetchAll($query);

        // 填充所有月份
        $result = array();
        for ($i = 1; $i <= 12; $i++) {
            $month = str_pad($i, 2, '0', STR_PAD_LEFT);
            $result[$month] = 0;
        }

        foreach ($rows as $row) {
            $result[$row['month']] = intval($row['count']);
        }

        return $result;
    }

    /**
     * 按周统计文章数量
     *
     * @return array
     */
    public function getPostsByWeek()
    {
        $query = $this->db->select('WEEK(FROM_UNIXTIME(created), 1) as week, COUNT(*) as count')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('created >= ?', $this->startTime)
            ->where('created <= ?', $this->endTime)
            ->where($this->getStatusCondition())
            ->group('week')
            ->order('week', Typecho_Db::SORT_ASC);

        $rows = $this->db->fetchAll($query);

        $result = array();
        foreach ($rows as $row) {
            $result['第' . $row['week'] . '周'] = intval($row['count']);
        }

        return $result;
    }

    /**
     * 按时段统计文章发布分布
     *
     * @return array
     */
    public function getPostsByHour()
    {
        $query = $this->db->select('FROM_UNIXTIME(created, "%H") as hour, COUNT(*) as count')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('created >= ?', $this->startTime)
            ->where('created <= ?', $this->endTime)
            ->where($this->getStatusCondition())
            ->group('hour')
            ->order('hour', Typecho_Db::SORT_ASC);

        $rows = $this->db->fetchAll($query);

        // 按时段分组
        $periods = array(
            '凌晨 (0-6点)' => 0,
            '上午 (6-12点)' => 0,
            '下午 (12-18点)' => 0,
            '晚上 (18-24点)' => 0
        );

        foreach ($rows as $row) {
            $hour = intval($row['hour']);
            $count = intval($row['count']);

            if ($hour >= 0 && $hour < 6) {
                $periods['凌晨 (0-6点)'] += $count;
            } elseif ($hour >= 6 && $hour < 12) {
                $periods['上午 (6-12点)'] += $count;
            } elseif ($hour >= 12 && $hour < 18) {
                $periods['下午 (12-18点)'] += $count;
            } else {
                $periods['晚上 (18-24点)'] += $count;
            }
        }

        return $periods;
    }

    /**
     * 获取总字数
     *
     * @return int
     */
    public function getTotalWords()
    {
        $query = $this->db->select('text')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('created >= ?', $this->startTime)
            ->where('created <= ?', $this->endTime)
            ->where($this->getStatusCondition());

        $rows = $this->db->fetchAll($query);
        $totalWords = 0;

        foreach ($rows as $row) {
            // 去除HTML标签和Markdown语法后统计字数
            $text = strip_tags($row['text']);
            $text = preg_replace('/\s+/', '', $text);
            $totalWords += mb_strlen($text, 'UTF-8');
        }

        return $totalWords;
    }

    /**
     * 获取平均文章字数
     *
     * @return int
     */
    public function getAverageWords()
    {
        $totalPosts = $this->getTotalPosts();
        if ($totalPosts === 0) {
            return 0;
        }

        return intval($this->getTotalWords() / $totalPosts);
    }

    /**
     * 获取最长文章
     *
     * @return array|null
     */
    public function getLongestPost()
    {
        $query = $this->db->select('cid, title, text, slug, created')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('created >= ?', $this->startTime)
            ->where('created <= ?', $this->endTime)
            ->where($this->getStatusCondition());

        $rows = $this->db->fetchAll($query);

        $longest = null;
        $maxWords = 0;

        foreach ($rows as $row) {
            $text = strip_tags($row['text']);
            $text = preg_replace('/\s+/', '', $text);
            $words = mb_strlen($text, 'UTF-8');

            if ($words > $maxWords) {
                $maxWords = $words;
                $longest = array(
                    'cid' => $row['cid'],
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'words' => $words,
                    'created' => $row['created']
                );
            }
        }

        return $longest;
    }

    /**
     * 获取最短文章
     *
     * @return array|null
     */
    public function getShortestPost()
    {
        $query = $this->db->select('cid, title, text, slug, created')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('created >= ?', $this->startTime)
            ->where('created <= ?', $this->endTime)
            ->where($this->getStatusCondition());

        $rows = $this->db->fetchAll($query);

        $shortest = null;
        $minWords = PHP_INT_MAX;

        foreach ($rows as $row) {
            $text = strip_tags($row['text']);
            $text = preg_replace('/\s+/', '', $text);
            $words = mb_strlen($text, 'UTF-8');

            if ($words < $minWords && $words > 0) {
                $minWords = $words;
                $shortest = array(
                    'cid' => $row['cid'],
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'words' => $words,
                    'created' => $row['created']
                );
            }
        }

        return $shortest;
    }

    /**
     * 获取分类分布
     *
     * @return array
     */
    public function getCategoryDistribution()
    {
        $query = $this->db->select('m.name, m.slug, COUNT(r.cid) as count')
            ->from('table.metas', 'm')
            ->join('table.relationships', 'r', 'm.mid = r.mid')
            ->join('table.contents', 'c', 'r.cid = c.cid')
            ->where('m.type = ?', 'category')
            ->where('c.type = ?', 'post')
            ->where('c.created >= ?', $this->startTime)
            ->where('c.created <= ?', $this->endTime)
            ->where('c.' . $this->getStatusCondition())
            ->group('m.mid')
            ->order('count', Typecho_Db::SORT_DESC);

        $rows = $this->db->fetchAll($query);

        $result = array();
        foreach ($rows as $row) {
            $result[] = array(
                'name' => $row['name'],
                'slug' => $row['slug'],
                'count' => intval($row['count'])
            );
        }

        return $result;
    }

    /**
     * 获取标签分布
     *
     * @return array
     */
    public function getTagDistribution()
    {
        $query = $this->db->select('m.name, m.slug, COUNT(r.cid) as count')
            ->from('table.metas', 'm')
            ->join('table.relationships', 'r', 'm.mid = r.mid')
            ->join('table.contents', 'c', 'r.cid = c.cid')
            ->where('m.type = ?', 'tag')
            ->where('c.type = ?', 'post')
            ->where('c.created >= ?', $this->startTime)
            ->where('c.created <= ?', $this->endTime)
            ->where('c.' . $this->getStatusCondition())
            ->group('m.mid')
            ->order('count', Typecho_Db::SORT_DESC);

        $rows = $this->db->fetchAll($query);

        $result = array();
        foreach ($rows as $row) {
            $result[] = array(
                'name' => $row['name'],
                'slug' => $row['slug'],
                'count' => intval($row['count'])
            );
        }

        return $result;
    }

    /**
     * 获取热门标签Top N
     *
     * @param int $limit 限制数量
     * @return array
     */
    public function getTopTags($limit = null)
    {
        $limit = $limit ?: $this->topLimit;
        $tags = $this->getTagDistribution();
        return array_slice($tags, 0, $limit);
    }

    /**
     * 获取浏览量排行
     *
     * @param int $limit 限制数量
     * @return array
     */
    public function getTopPostsByViews($limit = null)
    {
        $limit = $limit ?: $this->topLimit;

        // 检查是否有views字段（需要统计插件支持）
        $query = $this->db->select('cid, title, slug, created, views')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('created >= ?', $this->startTime)
            ->where('created <= ?', $this->endTime)
            ->where($this->getStatusCondition())
            ->order('views', Typecho_Db::SORT_DESC)
            ->limit($limit);

        try {
            $rows = $this->db->fetchAll($query);
            $result = array();

            foreach ($rows as $row) {
                $result[] = array(
                    'cid' => $row['cid'],
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'views' => isset($row['views']) ? intval($row['views']) : 0,
                    'created' => $row['created']
                );
            }

            return $result;
        } catch (Exception $e) {
            // 如果没有views字段，返回空数组
            return array();
        }
    }

    /**
     * 获取评论数排行
     *
     * @param int $limit 限制数量
     * @return array
     */
    public function getTopPostsByComments($limit = null)
    {
        $limit = $limit ?: $this->topLimit;

        $query = $this->db->select('c.cid, c.title, c.slug, c.created, c.commentsNum')
            ->from('table.contents', 'c')
            ->where('c.type = ?', 'post')
            ->where('c.created >= ?', $this->startTime)
            ->where('c.created <= ?', $this->endTime)
            ->where('c.' . $this->getStatusCondition())
            ->order('c.commentsNum', Typecho_Db::SORT_DESC)
            ->limit($limit);

        $rows = $this->db->fetchAll($query);

        $result = array();
        foreach ($rows as $row) {
            $result[] = array(
                'cid' => $row['cid'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'comments' => intval($row['commentsNum']),
                'created' => $row['created']
            );
        }

        return $result;
    }

    /**
     * 获取总评论数
     *
     * @return int
     */
    public function getTotalComments()
    {
        $query = $this->db->select('COUNT(*) as total')
            ->from('table.comments', 'cm')
            ->join('table.contents', 'c', 'cm.cid = c.cid')
            ->where('c.type = ?', 'post')
            ->where('cm.created >= ?', $this->startTime)
            ->where('cm.created <= ?', $this->endTime)
            ->where('cm.status = ?', 'approved');

        $result = $this->db->fetchRow($query);
        return intval($result['total']);
    }

    /**
     * 获取平均评论数
     *
     * @return float
     */
    public function getAverageComments()
    {
        $totalPosts = $this->getTotalPosts();
        if ($totalPosts === 0) {
            return 0;
        }

        return round($this->getTotalComments() / $totalPosts, 2);
    }

    /**
     * 获取活跃评论者排行
     *
     * @param int $limit 限制数量
     * @return array
     */
    public function getTopCommenters($limit = null)
    {
        $limit = $limit ?: $this->topLimit;

        $query = $this->db->select('cm.author, cm.mail, cm.url, COUNT(*) as count')
            ->from('table.comments', 'cm')
            ->join('table.contents', 'c', 'cm.cid = c.cid')
            ->where('c.type = ?', 'post')
            ->where('cm.created >= ?', $this->startTime)
            ->where('cm.created <= ?', $this->endTime)
            ->where('cm.status = ?', 'approved')
            ->group('cm.mail')
            ->order('count', Typecho_Db::SORT_DESC)
            ->limit($limit);

        $rows = $this->db->fetchAll($query);

        $result = array();
        foreach ($rows as $row) {
            $result[] = array(
                'author' => $row['author'],
                'mail' => $row['mail'],
                'url' => $row['url'],
                'count' => intval($row['count'])
            );
        }

        return $result;
    }

    /**
     * 获取总浏览量
     *
     * @return int
     */
    public function getTotalViews()
    {
        try {
            $query = $this->db->select('SUM(views) as total')
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('created >= ?', $this->startTime)
                ->where('created <= ?', $this->endTime)
                ->where($this->getStatusCondition());

            $result = $this->db->fetchRow($query);
            return intval($result['total']);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 获取年度对比数据
     *
     * @return array
     */
    public function getYearComparison()
    {
        $currentYear = $this->year;
        $previousYear = $currentYear - 1;

        // 当前年份数据
        $currentData = array(
            'year' => $currentYear,
            'posts' => $this->getTotalPosts(),
            'words' => $this->getTotalWords(),
            'comments' => $this->getTotalComments(),
            'views' => $this->getTotalViews()
        );

        // 上一年数据
        $this->setYear($previousYear);
        $previousData = array(
            'year' => $previousYear,
            'posts' => $this->getTotalPosts(),
            'words' => $this->getTotalWords(),
            'comments' => $this->getTotalComments(),
            'views' => $this->getTotalViews()
        );

        // 恢复当前年份
        $this->setYear($currentYear);

        // 计算增长率
        $growth = array(
            'posts' => $this->calculateGrowth($previousData['posts'], $currentData['posts']),
            'words' => $this->calculateGrowth($previousData['words'], $currentData['words']),
            'comments' => $this->calculateGrowth($previousData['comments'], $currentData['comments']),
            'views' => $this->calculateGrowth($previousData['views'], $currentData['views'])
        );

        return array(
            'current' => $currentData,
            'previous' => $previousData,
            'growth' => $growth
        );
    }

    /**
     * 计算增长率
     *
     * @param int $previous 上期数据
     * @param int $current 本期数据
     * @return float
     */
    private function calculateGrowth($previous, $current)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round(($current - $previous) / $previous * 100, 2);
    }

    /**
     * 获取所有统计数据
     *
     * @return array
     */
    public function getAllStats()
    {
        return array(
            'year' => $this->year,
            'overview' => array(
                'totalPosts' => $this->getTotalPosts(),
                'totalWords' => $this->getTotalWords(),
                'averageWords' => $this->getAverageWords(),
                'totalComments' => $this->getTotalComments(),
                'averageComments' => $this->getAverageComments(),
                'totalViews' => $this->getTotalViews()
            ),
            'timeline' => array(
                'byMonth' => $this->getPostsByMonth(),
                'byWeek' => $this->getPostsByWeek(),
                'byHour' => $this->getPostsByHour()
            ),
            'content' => array(
                'longestPost' => $this->getLongestPost(),
                'shortestPost' => $this->getShortestPost(),
                'categories' => $this->getCategoryDistribution(),
                'tags' => $this->getTagDistribution(),
                'topTags' => $this->getTopTags()
            ),
            'popularity' => array(
                'topByViews' => $this->getTopPostsByViews(),
                'topByComments' => $this->getTopPostsByComments(),
                'topCommenters' => $this->getTopCommenters()
            ),
            'comparison' => $this->getYearComparison()
        );
    }
}
