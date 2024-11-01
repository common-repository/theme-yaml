<?php
declare(strict_types=1);
/**
 *
 * @link              https://saschapaukner.de
 * @since             1.0.2
 * @package           Theme_Yaml
 *
 * @wordpress-plugin
 * Plugin Name:       Theme Yaml
 * Description:       Theme Yaml adds support for managing your theme settings in theme.json via a theme.yaml file
 * Version:           1.3.0
 * Author:            Sascha Paukner
 * Author URI:        https://saschapaukner.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       theme-yaml
 * Domain Path:       /languages
 **/

namespace ThemeYaml;
use False\MyClass;
use Symfony\Component\Yaml\Yaml;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

function deactivate()
{
    // initiate instance
    $ThemeYaml = new ThemeYaml();

    delete_option($ThemeYaml->plugin_name);
}

/**
 * Loading ThemeYaml
 *
 * @return void
 */
function init()
{
    require_once(plugin_dir_path(__FILE__) . '/lib/autoload.php');

    // initiate instance
    $ThemeYaml = new ThemeYaml();
    // call the register function
    $ThemeYaml->register();
}

register_deactivation_hook(__FILE__, 'ThemeYaml\deactivate');
add_action('plugins_loaded', 'ThemeYaml\init');

class ThemeYaml
{

    public $plugin_name = 'theme-yaml';

    protected $domain = 'theme-yaml';

    /**
     * Registers our plugin with WordPress.
     */
    public static function register()
    {
        $plugin = new self();

        // Actions
        add_action('plugins_loaded', array($plugin, 'load_textdomain'));
        add_action( 'init', array( $plugin, 'convertYamlToJson' ));
    }

    public function load_textdomain()
    {
        load_plugin_textdomain(
          $this->domain,
          FALSE,
          dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    public function convertYamlToJson()
    {
        $lastModifiedTS = 0;
        $theme_paths = [];
        $theme_paths['parent'] = get_template_directory();
        $flags = JSON_UNESCAPED_SLASHES;
        $prettyPrint = apply_filters('theme_yaml_json_pretty_print', false);
        if($prettyPrint) {
            $flags = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
        }

        if (is_child_theme()) {
            $theme_paths['child'] = get_stylesheet_directory();
        }

        foreach ($theme_paths as $theme => $theme_path) {
            $themeJsonPath = $theme_path . '/theme.json';
            $themeJsonBackupPath = $theme_path . '/theme-json-backup.json';
            $themeYamlPath = $theme_path . '/theme.yaml';

            // take a backup of any existing theme.json before doing anything else
            // so users do not loose their theme.json data if they just create an empty theme.yaml for instance
            if (!file_exists($themeJsonBackupPath) && file_exists($themeYamlPath) && file_exists($themeJsonPath)) {
                copy($themeJsonPath, $themeJsonBackupPath);
            }

            if (file_exists($themeYamlPath)) {
                $modifiedTS = filemtime($themeYamlPath);
                $options = get_option($this->plugin_name);

                if ($options) {
                    $lastModifiedTS = (int) $options[$theme];
                } else {
                    $options = [];
                    // update the timestamp
                    $options[$theme] = $modifiedTS;
                    update_option($this->plugin_name, $options);
                }

                if ($modifiedTS > $lastModifiedTS || !file_exists($themeJsonPath)) {
                    // update theme.json
                    $themeObject = Yaml::parseFile($themeYamlPath, Yaml::PARSE_OBJECT_FOR_MAP);
                    $themeJson = json_encode($themeObject, $flags);
                    file_put_contents($themeJsonPath, $themeJson);

                    // update the timestamp
                    $options[$theme] = $modifiedTS;
                    update_option($this->plugin_name, $options);
                }
            }
        }
    }
}
