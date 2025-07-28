<?php
/**
 * MCP Server Settings Class
 *
 * @package Mcp-Server-For-Wordpress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCP_Server_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
        add_action( 'admin_footer-settings_page_mcp-server', [ $this, 'admin_footer_scripts' ] );
        add_action( 'wp_ajax_mcp_get_terms_for_taxonomy', [ $this, 'ajax_get_terms_for_taxonomy' ] );
    }

    public function add_admin_menu() {
        add_options_page( 'MCP Server Settings', 'MCP Server', 'manage_options', 'mcp-server', [ $this, 'options_page_html' ] );
    }

    public function settings_init() {
        register_setting( 'mcpServerPage', 'mcp_server_settings' );

        add_settings_section( 'mcp_server_section_auth', '認証設定', null, 'mcpServerPage' );
        add_settings_field( 'mcp_server_secret_token', '秘密のトークン', [ $this, 'secret_token_render' ], 'mcpServerPage', 'mcp_server_section_auth' );

        add_settings_section( 'mcp_server_section_ninja', 'Ninja Forms 連携設定', null, 'mcpServerPage' );
        add_settings_field( 'mcp_ninja_form_id', '連携フォーム', [ $this, 'ninja_form_id_render' ], 'mcpServerPage', 'mcp_server_section_ninja' );
        add_settings_field( 'mcp_ninja_field_mappings', 'フィールドマッピング (任意)', [ $this, 'ninja_field_mappings_render' ], 'mcpServerPage', 'mcp_server_section_ninja' );
        
        add_settings_section( 'mcp_server_section_reports', 'レポート一覧設定', null, 'mcpServerPage' );
        add_settings_field( 'mcp_report_taxonomy', '対象タクソノミー', [ $this, 'report_taxonomy_render' ], 'mcpServerPage', 'mcp_server_section_reports' );
        add_settings_field( 'mcp_report_term', '対象ターム', [ $this, 'report_term_render' ], 'mcpServerPage', 'mcp_server_section_reports' );
    }

    public function secret_token_render() {
        $options = get_option( 'mcp_server_settings' );
        $token   = $options['mcp_server_secret_token'] ?? '';
        ?>
        <input type='text' id='mcp_server_secret_token_field' name='mcp_server_settings[mcp_server_secret_token]' value='<?php echo esc_attr( $token ); ?>' style="width: 400px; margin-right: 10px;">
        <button type="button" id="mcp-generate-token-btn" class="button">トークンを生成</button>
        <p class="description">APIを認証するための秘密の文字列です。</p>
        <?php
    }

    public function ninja_form_id_render() {
        $options          = get_option( 'mcp_server_settings' );
        $selected_form_id = $options['mcp_ninja_form_id'] ?? '';
        if ( ! function_exists( 'Ninja_Forms' ) ) {
            echo '<p style="color: red;">Ninja Formsプラグインが有効ではありません。</p>';
            return;
        }
        $forms = Ninja_Forms()->form()->get_forms();
        ?>
        <select id="mcp_ninja_form_id_select" name='mcp_server_settings[mcp_ninja_form_id]'>
            <option value="">-- フォームを選択 --</option>
            <?php foreach ( $forms as $form ) : ?>
                <option value="<?php echo esc_attr( $form->get_id() ); ?>" <?php selected( $selected_form_id, $form->get_id() ); ?>>
                    <?php echo esc_html( $form->get_setting( 'title' ) ); ?> (ID: <?php echo esc_attr( $form->get_id() ); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="mcp-show-schema-btn" class="button" style="margin-left: 10px;">現在のスキーマを表示/隠す</button>
        <pre id="mcp-schema-monitor" style="display: none; background-color: #f0f0f1; padding: 15px; border-radius: 4px; margin-top: 10px; white-space: pre-wrap; word-break: break-all;"></pre>
        <?php
    }

    public function ninja_field_mappings_render() {
        $options  = get_option( 'mcp_server_settings' );
        $mappings = $options['mcp_ninja_field_mappings'] ?? [ [ 'ai_key' => '', 'nf_key' => '' ] ];
        ?>
        <div id="mcp-mappings-wrapper">
            <?php foreach ( $mappings as $index => $mapping ) : ?>
                <div class="mcp-mapping-row" style="margin-bottom: 10px;">
                    <label>AIデータキー: </label>
                    <input type="text" name="mcp_server_settings[mcp_ninja_field_mappings][<?php echo $index; ?>][ai_key]" value="<?php echo esc_attr( $mapping['ai_key'] ); ?>" placeholder="例: user_name">
                    <label>Ninja Forms フィールドキー: </label>
                    <input type="text" name="mcp_server_settings[mcp_ninja_field_mappings][<?php echo $index; ?>][nf_key]" value="<?php echo esc_attr( $mapping['nf_key'] ); ?>" placeholder="例: name">
                    <button type="button" class="button mcp-remove-mapping-btn">削除</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="mcp-add-mapping-btn" class="button">マッピングを追加</button>
        <p class="description">AIが送るキー名とフォームのキー名が違う場合のみ、ここで翻訳ルールを設定します。</p>
        <?php
    }

    public function report_taxonomy_render() {
        $options = get_option( 'mcp_server_settings' );
        $selected_tax = $options['mcp_report_taxonomy'] ?? '';
        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
        ?>
        <select id="mcp_report_taxonomy_select" name='mcp_server_settings[mcp_report_taxonomy]'>
            <option value="">-- タクソノミーを選択 --</option>
            <?php foreach ( $taxonomies as $taxonomy ) : ?>
                <option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $selected_tax, $taxonomy->name ); ?>>
                    <?php echo esc_html( $taxonomy->label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function report_term_render() {
        $options = get_option( 'mcp_server_settings' );
        $selected_term = $options['mcp_report_term'] ?? '';
        ?>
        <select id="mcp_report_term_select" name='mcp_server_settings[mcp_report_term]'>
            <option value="">-- 上のタクソノミーを選択してください --</option>
        </select>
        <p class="description">このタームに属する記事の中から、ファイルを持つものが一覧として公開されます。</p>
        <?php
    }

    public function options_page_html() {
        ?>
        <div class="wrap">
            <h1>MCP Server 設定</h1>
            <form action='options.php' method='post'>
                <?php
                settings_fields( 'mcpServerPage' );
                do_settings_sections( 'mcpServerPage' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function ajax_get_terms_for_taxonomy() {
        check_ajax_referer( 'mcp_admin_nonce', 'nonce' );
        $taxonomy = sanitize_text_field( $_POST['taxonomy'] );
        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        wp_send_json_success( $terms );
    }
    
    public function admin_footer_scripts() {
        $options = get_option( 'mcp_server_settings' );
        $selected_tax = $options['mcp_report_taxonomy'] ?? '';
        $selected_term = $options['mcp_report_term'] ?? '';
        ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('mcp-generate-token-btn')?.addEventListener('click', function() {
                const tokenField = document.getElementById('mcp_server_secret_token_field');
                const randomBytes = new Uint8Array(32);
                window.crypto.getRandomValues(randomBytes);
                tokenField.value = Array.from(randomBytes).map(b => b.toString(16).padStart(2, '0')).join('');
            });

            document.getElementById('mcp-show-schema-btn')?.addEventListener('click', async function() {
                const monitor = document.getElementById('mcp-schema-monitor');
                if (monitor.style.display === 'block') {
                    monitor.style.display = 'none';
                    return;
                }
                const formId = document.getElementById('mcp_ninja_form_id_select').value;
                const secret = document.getElementById('mcp_server_secret_token_field').value;
                if (!formId) {
                    monitor.textContent = '先に連携フォームを選択してください。';
                    monitor.style.display = 'block';
                    return;
                }
                if (!secret) {
                    monitor.textContent = '先に秘密のトークンを生成・保存してください。';
                    monitor.style.display = 'block';
                    return;
                }
                monitor.textContent = 'スキーマを取得中...';
                monitor.style.display = 'block';
                const timestamp = Math.floor(Date.now() / 1000);
                const body = JSON.stringify({ form_id: formId });
                const stringToSign = `${timestamp}.${body}`;
                const signature = CryptoJS.HmacSHA256(stringToSign, secret).toString(CryptoJS.enc.Hex);
                const apiUrl = `<?php echo esc_url_raw( home_url( '/wp-json/mcp/v1/inquiry/schema' ) ); ?>`;
                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'x-mcp-timestamp': timestamp,
                            'x-mcp-signature': signature
                        },
                        body: body
                    });
                    const data = await response.json();
                    if (response.ok) {
                        monitor.textContent = JSON.stringify(data, null, 2);
                    } else {
                        if (data.code === 'mcp_config_error') {
                            monitor.textContent = 'エラー: トークンがまだ保存されていません。[変更を保存]ボタンを押してください。';
                        } else {
                            monitor.textContent = `エラー: ${data.message || '取得に失敗しました。'}`;
                        }
                    }
                } catch (error) {
                    monitor.textContent = `通信エラー: ${error.message}`;
                }
            });

            const wrapper = document.getElementById('mcp-mappings-wrapper');
            document.getElementById('mcp-add-mapping-btn')?.addEventListener('click', function() {
                const index = wrapper.children.length;
                const newRow = document.createElement('div');
                newRow.className = 'mcp-mapping-row';
                newRow.style.marginBottom = '10px';
                newRow.innerHTML = `<label>AIデータキー: </label><input type="text" name="mcp_server_settings[mcp_ninja_field_mappings][${index}][ai_key]" placeholder="例: user_name"> <label>Ninja Forms フィールドキー: </label><input type="text" name="mcp_server_settings[mcp_ninja_field_mappings][${index}][nf_key]" placeholder="例: name"> <button type="button" class="button mcp-remove-mapping-btn">削除</button>`;
                wrapper.appendChild(newRow);
            });
            wrapper.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('mcp-remove-mapping-btn')) {
                    e.target.closest('.mcp-mapping-row').remove();
                }
            });

            const taxSelect = document.getElementById('mcp_report_taxonomy_select');
            const termSelect = document.getElementById('mcp_report_term_select');

            function fetchTerms(taxonomy, selectedTermId) {
                if (!taxonomy) {
                    termSelect.innerHTML = '<option value="">-- 上のタクソノミーを選択してください --</option>';
                    return;
                }
                const data = new FormData();
                data.append('action', 'mcp_get_terms_for_taxonomy');
                data.append('taxonomy', taxonomy);
                data.append('nonce', '<?php echo wp_create_nonce( "mcp_admin_nonce" ); ?>');
                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            termSelect.innerHTML = '<option value="">-- タームを選択 --</option>';
                            result.data.forEach(term => {
                                const option = document.createElement('option');
                                option.value = term.term_id;
                                option.textContent = term.name;
                                if (selectedTermId && term.term_id == selectedTermId) {
                                    option.selected = true;
                                }
                                termSelect.appendChild(option);
                            });
                        }
                    });
            }
            taxSelect.addEventListener('change', function() { fetchTerms(this.value, null); });
            const initialTax = '<?php echo esc_js( $selected_tax ); ?>';
            const initialTerm = '<?php echo esc_js( $selected_term ); ?>';
            if (initialTax) { fetchTerms(initialTax, initialTerm); }
        });
        </script>
        <?php
    }
}
