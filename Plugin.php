<?php
/**
 * YearlySummary - Typecho 年度文章统计插件
 *
 * @package YearlySummary
 * @author xiangmingya
 * @version 1.1
 * @link https://xiangming.site/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class YearlySummary_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 插件版本号
     */
    const VERSION = '1.0.0';

    /**
     * 激活插件方法
     */
    public static function activate()
    {
        // 注册后台菜单
        Helper::addPanel(3, 'YearlySummary/views/admin.php', '年度统计', '年度文章统计', 'administrator');

        // 注册路由用于数据导出
        Helper::addRoute('yearly_summary_export', '/yearly-summary/export', 'YearlySummary_Action', 'export');
        Helper::addRoute('yearly_summary_api', '/yearly-summary/api', 'YearlySummary_Action', 'api');
        Helper::addRoute('yearly_summary_update', '/yearly-summary/check-update', 'YearlySummary_Action', 'checkUpdate');

        return _t('插件已激活，请在控制台查看年度统计');
    }

    /**
     * 禁用插件方法
     */
    public static function deactivate()
    {
        // 移除后台菜单
        Helper::removePanel(3, 'YearlySummary/views/admin.php');

        // 移除路由
        Helper::removeRoute('yearly_summary_export');
        Helper::removeRoute('yearly_summary_api');
        Helper::removeRoute('yearly_summary_update');

        // 清除更新缓存
        require_once __DIR__ . '/Update.php';
        YearlySummary_Update::clearCache();

        return _t('插件已禁用');
    }

    /**
     * 获取插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 显示当前版本和更新检查
        require_once __DIR__ . '/Update.php';
        $updateInfo = YearlySummary_Update::check();

        // 版本信息提示
        $versionHtml = '<div style="padding: 10px 15px; background: #f0f0f0; border-radius: 4px; margin-bottom: 10px;">';
        $versionHtml .= '<strong>当前版本：</strong>v' . self::VERSION;

        if ($updateInfo && $updateInfo['has_update']) {
            $versionHtml .= ' <span style="color: #e74c3c; margin-left: 15px;">⚠️ 发现新版本 v' . $updateInfo['latest_version'] . '</span>';
            $versionHtml .= ' <a href="' . $updateInfo['release_url'] . '" target="_blank" style="margin-left: 10px;">查看更新</a>';
        } else {
            $versionHtml .= ' <span style="color: #27ae60; margin-left: 15px;">✓ 已是最新版本</span>';
        }

        $versionHtml .= '</div>';

        $versionInfo = new Typecho_Widget_Helper_Form_Element_Text(
            'versionInfo',
            null,
            null,
            _t('版本信息'),
            $versionHtml
        );
        $form->addInput($versionInfo);

        // 默认显示条数
        $topLimit = new Typecho_Widget_Helper_Form_Element_Text(
            'topLimit',
            null,
            '10',
            _t('排行榜显示条数'),
            _t('设置排行榜默认显示的条目数量')
        );
        $form->addInput($topLimit);

        // 默认年份
        $defaultYear = new Typecho_Widget_Helper_Form_Element_Text(
            'defaultYear',
            null,
            date('Y'),
            _t('默认统计年份'),
            _t('设置默认统计的年份，留空则为当前年份')
        );
        $form->addInput($defaultYear);

        // 是否统计草稿
        $includeDraft = new Typecho_Widget_Helper_Form_Element_Radio(
            'includeDraft',
            array('0' => '否', '1' => '是'),
            '0',
            _t('是否统计草稿'),
            _t('选择是否将草稿文章纳入统计')
        );
        $form->addInput($includeDraft);

        // 图表主题色
        $chartColor = new Typecho_Widget_Helper_Form_Element_Text(
            'chartColor',
            null,
            '#667eea',
            _t('图表主题色'),
            _t('设置图表的主题颜色，使用十六进制颜色值')
        );
        $form->addInput($chartColor);

        // 更新检查开关
        $checkUpdate = new Typecho_Widget_Helper_Form_Element_Radio(
            'checkUpdate',
            array('1' => '开启', '0' => '关闭'),
            '1',
            _t('自动检查更新'),
            _t('开启后将自动检查插件更新（每12小时检查一次）')
        );
        $form->addInput($checkUpdate);

        // GitHub 仓库设置
        $githubRepo = new Typecho_Widget_Helper_Form_Element_Text(
            'githubRepo',
            null,
            'xiangmingya/YearlySummary',
            _t('GitHub 仓库'),
            _t('设置插件的 GitHub 仓库地址，格式：用户名/仓库名')
        );
        $form->addInput($githubRepo);
    }

    /**
     * 个人用户的配置面板
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
        // 暂无个人配置
    }

    /**
     * 获取插件配置
     */
    public static function getConfig($key = null)
    {
        $options = Helper::options();
        $config = $options->plugin('YearlySummary');

        if ($key !== null) {
            return isset($config->$key) ? $config->$key : null;
        }

        return $config;
    }

    /**
     * 获取插件版本
     */
    public static function getVersion()
    {
        return self::VERSION;
    }

    /**
     * 检查更新
     */
    public static function checkUpdate()
    {
        $config = self::getConfig();

        // 如果关闭了更新检查
        if (isset($config->checkUpdate) && $config->checkUpdate === '0') {
            return false;
        }

        require_once __DIR__ . '/Update.php';
        return YearlySummary_Update::check();
    }
}
