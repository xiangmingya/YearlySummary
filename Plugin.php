<?php
namespace TypechoPlugin\YearlySummary;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Radio;
use Widget\Options;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * YearlySummary - Typecho 年度文章统计插件
 *
 * @package YearlySummary
 * @author xiangmingya
 * @version 1.1.0
 * @link https://xiangming.site/
 */
class Plugin implements PluginInterface
{
    /**
     * 插件版本号
     */
    const VERSION = '1.1.0';

    /**
     * 激活插件方法
     */
    public static function activate()
    {
        // 添加后台菜单（1=控制台）
        Helper::addPanel(1, 'YearlySummary/Panel.php', '年度统计', '年度统计', 'administrator');

        return _t('插件已激活，可在控制台菜单中找到"年度统计"入口');
    }

    /**
     * 禁用插件方法
     */
    public static function deactivate()
    {
        // 移除后台菜单
        Helper::removePanel(1, 'YearlySummary/Panel.php');

        return _t('插件已禁用');
    }

    /**
     * 获取插件配置面板
     */
    public static function config(Form $form)
    {
        // 版本信息提示
        echo '<div style="padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; margin-bottom: 20px; color: #fff;">';
        echo '<div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">YearlySummary 年度统计</div>';
        echo '<div style="margin-bottom: 10px;">当前版本：v' . self::VERSION . '</div>';
        echo '<div style="font-size: 14px; opacity: 0.9;">请在左侧菜单「控制台 → 年度统计」中查看统计数据</div>';
        echo '</div>';

        // 默认显示条数
        $topLimit = new Text('topLimit', null, '10',
            _t('排行榜显示条数'),
            _t('设置排行榜默认显示的条目数量'));
        $form->addInput($topLimit);

        // 默认年份
        $defaultYear = new Text('defaultYear', null, date('Y'),
            _t('默认统计年份'),
            _t('设置默认统计的年份，留空则为当前年份'));
        $form->addInput($defaultYear);

        // 是否统计草稿
        $includeDraft = new Radio('includeDraft',
            ['0' => _t('否'), '1' => _t('是')],
            '0',
            _t('是否统计草稿'),
            _t('选择是否将草稿文章纳入统计'));
        $form->addInput($includeDraft);

        // 图表主题色
        $chartColor = new Text('chartColor', null, '#667eea',
            _t('图表主题色'),
            _t('设置图表的主题颜色，使用十六进制颜色值'));
        $form->addInput($chartColor);
    }

    /**
     * 个人用户的配置面板
     */
    public static function personalConfig(Form $form)
    {
        // 暂无个人配置
    }

    /**
     * 获取插件配置
     */
    public static function getConfig($key = null)
    {
        try {
            $config = Options::alloc()->plugin('YearlySummary');

            if ($key !== null) {
                return isset($config->$key) ? $config->$key : null;
            }

            return $config;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 获取插件版本
     */
    public static function getVersion()
    {
        return self::VERSION;
    }
}
