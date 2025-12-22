<?php
/**
 * YearlySummary 版本更新检查类
 *
 * @package YearlySummary
 * @author xiangmingya
 * @version 1.1
 * @link https://xiangming.site/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class YearlySummary_Update
{
    /**
     * 当前插件版本
     */
    const CURRENT_VERSION = '1.0.0';

    /**
     * GitHub 仓库信息
     * 请修改为你的实际仓库地址
     */
    const GITHUB_USER = 'xiangmingya';
    const GITHUB_REPO = 'YearlySummary';

    /**
     * 缓存时间（秒）- 默认12小时检查一次
     */
    const CACHE_TIME = 43200;

    /**
     * 检查更新
     *
     * @return array|false 返回更新信息或false
     */
    public static function check()
    {
        // 获取缓存的版本信息
        $cached = self::getCache();

        if ($cached !== false) {
            return $cached;
        }

        // 从 GitHub 获取最新版本
        $latestInfo = self::fetchLatestRelease();

        if ($latestInfo === false) {
            return false;
        }

        // 缓存结果
        self::setCache($latestInfo);

        return $latestInfo;
    }

    /**
     * 从 GitHub API 获取最新发布版本
     *
     * @return array|false
     */
    private static function fetchLatestRelease()
    {
        $url = 'https://api.github.com/repos/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/releases/latest';

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => array(
                    'User-Agent: YearlySummary-Plugin',
                    'Accept: application/vnd.github.v3+json'
                ),
                'timeout' => 10
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ));

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return false;
            }

            $data = json_decode($response, true);

            if (!isset($data['tag_name'])) {
                return false;
            }

            // 解析版本号（去掉v前缀）
            $latestVersion = ltrim($data['tag_name'], 'vV');

            return array(
                'has_update' => version_compare($latestVersion, self::CURRENT_VERSION, '>'),
                'current_version' => self::CURRENT_VERSION,
                'latest_version' => $latestVersion,
                'release_name' => isset($data['name']) ? $data['name'] : '',
                'release_notes' => isset($data['body']) ? $data['body'] : '',
                'release_url' => isset($data['html_url']) ? $data['html_url'] : '',
                'download_url' => isset($data['zipball_url']) ? $data['zipball_url'] : '',
                'published_at' => isset($data['published_at']) ? $data['published_at'] : '',
                'check_time' => time()
            );
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 从 GitHub raw 文件获取版本（备用方案）
     * 需要在仓库根目录创建 version.json 文件
     *
     * @return array|false
     */
    public static function fetchFromVersionFile()
    {
        $url = 'https://raw.githubusercontent.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/main/version.json';

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => 'User-Agent: YearlySummary-Plugin',
                'timeout' => 10
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false
            )
        ));

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return false;
            }

            $data = json_decode($response, true);

            if (!isset($data['version'])) {
                return false;
            }

            return array(
                'has_update' => version_compare($data['version'], self::CURRENT_VERSION, '>'),
                'current_version' => self::CURRENT_VERSION,
                'latest_version' => $data['version'],
                'release_name' => isset($data['name']) ? $data['name'] : '',
                'release_notes' => isset($data['changelog']) ? $data['changelog'] : '',
                'release_url' => isset($data['url']) ? $data['url'] : 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/releases',
                'download_url' => isset($data['download']) ? $data['download'] : '',
                'published_at' => isset($data['date']) ? $data['date'] : '',
                'check_time' => time()
            );
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取缓存的版本信息
     *
     * @return array|false
     */
    private static function getCache()
    {
        $cacheFile = self::getCacheFile();

        if (!file_exists($cacheFile)) {
            return false;
        }

        $content = file_get_contents($cacheFile);
        $data = json_decode($content, true);

        if (!$data || !isset($data['check_time'])) {
            return false;
        }

        // 检查缓存是否过期
        if (time() - $data['check_time'] > self::CACHE_TIME) {
            return false;
        }

        return $data;
    }

    /**
     * 设置缓存
     *
     * @param array $data 版本信息
     */
    private static function setCache($data)
    {
        $cacheFile = self::getCacheFile();
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        file_put_contents($cacheFile, json_encode($data));
    }

    /**
     * 清除缓存
     */
    public static function clearCache()
    {
        $cacheFile = self::getCacheFile();

        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    /**
     * 获取缓存文件路径
     *
     * @return string
     */
    private static function getCacheFile()
    {
        return __TYPECHO_ROOT_DIR__ . '/usr/plugins/YearlySummary/cache/update.json';
    }

    /**
     * 强制检查更新（忽略缓存）
     *
     * @return array|false
     */
    public static function forceCheck()
    {
        self::clearCache();
        return self::check();
    }

    /**
     * 获取当前版本
     *
     * @return string
     */
    public static function getCurrentVersion()
    {
        return self::CURRENT_VERSION;
    }

    /**
     * 渲染更新提示HTML
     *
     * @param array $updateInfo 更新信息
     * @return string
     */
    public static function renderNotice($updateInfo)
    {
        if (!$updateInfo || !$updateInfo['has_update']) {
            return '';
        }

        $html = '<div class="ys-update-notice">';
        $html .= '<div class="ys-update-icon">🎉</div>';
        $html .= '<div class="ys-update-content">';
        $html .= '<div class="ys-update-title">发现新版本！</div>';
        $html .= '<div class="ys-update-info">';
        $html .= '当前版本：<strong>v' . htmlspecialchars($updateInfo['current_version']) . '</strong> → ';
        $html .= '最新版本：<strong>v' . htmlspecialchars($updateInfo['latest_version']) . '</strong>';
        $html .= '</div>';

        if (!empty($updateInfo['release_name'])) {
            $html .= '<div class="ys-update-name">' . htmlspecialchars($updateInfo['release_name']) . '</div>';
        }

        if (!empty($updateInfo['release_notes'])) {
            $notes = strip_tags($updateInfo['release_notes']);
            if (mb_strlen($notes) > 200) {
                $notes = mb_substr($notes, 0, 200) . '...';
            }
            $html .= '<div class="ys-update-notes">' . nl2br(htmlspecialchars($notes)) . '</div>';
        }

        $html .= '</div>';
        $html .= '<div class="ys-update-actions">';

        if (!empty($updateInfo['release_url'])) {
            $html .= '<a href="' . htmlspecialchars($updateInfo['release_url']) . '" target="_blank" class="btn btn-primary">查看详情</a>';
        }

        if (!empty($updateInfo['download_url'])) {
            $html .= '<a href="' . htmlspecialchars($updateInfo['download_url']) . '" target="_blank" class="btn">下载更新</a>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * 获取更新提示的CSS样式
     *
     * @return string
     */
    public static function getNoticeStyles()
    {
        return '
        .ys-update-notice {
            display: flex;
            align-items: flex-start;
            padding: 16px 20px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            color: #fff;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .ys-update-icon {
            font-size: 32px;
            margin-right: 16px;
            line-height: 1;
        }
        .ys-update-content {
            flex: 1;
        }
        .ys-update-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .ys-update-info {
            font-size: 14px;
            opacity: 0.95;
            margin-bottom: 6px;
        }
        .ys-update-name {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 6px;
        }
        .ys-update-notes {
            font-size: 13px;
            opacity: 0.85;
            padding: 10px;
            background: rgba(255,255,255,0.15);
            border-radius: 4px;
            margin-top: 8px;
            max-height: 100px;
            overflow-y: auto;
        }
        .ys-update-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-left: 16px;
        }
        .ys-update-actions .btn {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            text-align: center;
            transition: all 0.2s;
        }
        .ys-update-actions .btn-primary {
            background: #fff;
            color: #667eea;
        }
        .ys-update-actions .btn-primary:hover {
            background: #f0f0f0;
        }
        .ys-update-actions .btn:not(.btn-primary) {
            background: rgba(255,255,255,0.2);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .ys-update-actions .btn:not(.btn-primary):hover {
            background: rgba(255,255,255,0.3);
        }
        ';
    }
}
