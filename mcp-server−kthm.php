<?php
/**
 * Plugin Name:       MCP Server for WordPress
 * Plugin URI:        https://example.com/
 * Description:       AIエージェントと連携するためのMCPサーバー機能を提供します。
 * Version:           3.0.0 (Refactored)
 * Author:            (あなたの名前)
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mcp-server
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 定数を定義
define( 'MCP_SERVER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// 必要なファイルを読み込む
require_once MCP_SERVER_PLUGIN_DIR . 'includes/class-mcp-server-settings.php';
require_once MCP_SERVER_PLUGIN_DIR . 'includes/class-mcp-server-api.php';

// プラグインのメインクラスを初期化
function mcp_server_run() {
    new MCP_Server_Settings();
    new MCP_Server_API();
}
mcp_server_run();
