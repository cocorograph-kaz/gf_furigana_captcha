<?php
/**
 * Plugin Name: GF Furigana Captcha
 * Description: Gravity Forms向けのふりがな入力専用フィールドプラグイン。姓・名のふりがな入力を安全に管理し、ひらがなのみの入力を強制するバリデーション機能を備えています。
 * Version: 1.0.0
 * Author: Kazuhiro Nakamura
 * Requires at least: 6.7
 * Requires PHP: 7.4
 */

// Gravity Formsが有効でない場合は何もしない
if ( ! class_exists( 'GFForms' ) ) {
    return;
}

// Gravity Forms読み込み後に初期化
add_action( 'gform_loaded', 'gf_furigana_captcha_init', 10 );
function gf_furigana_captcha_init() {

    // Gravity Formsのフィールドフレームワークが利用可能かチェック
    if ( ! class_exists( 'GF_Field' ) || ! method_exists( 'GF_Fields', 'register' ) ) {
        return;
    }

    // 1. カスタムフィールドクラス定義
    class GF_Field_Furigana extends GF_Field {
        public $type = 'furigana';  // フィールドタイプ名
        public $isRequired = false;

        // 複合フィールドとしての入力項目を定義する
        public $inputs = array(
            array(
                'id' => null,  // コンストラクタで動的に設定
                'label' => '姓（せい）のふりがな',
                'name' => 'sei'
            ),
            array(
                'id' => null,  // コンストラクタで動的に設定
                'label' => '名（めい）のふりがな',
                'name' => 'mei'
            )
        );

        public function __construct($data = array()) {
            parent::__construct($data);

            // 入力項目のIDを動的に設定
            $this->inputs = array(
                array(
                    'id' => $this->id ? $this->id . '.1' : null,
                    'label' => '姓（せい）のふりがな',
                    'name' => 'sei'
                ),
                array(
                    'id' => $this->id ? $this->id . '.2' : null,
                    'label' => '名（めい）のふりがな',
                    'name' => 'mei'
                )
            );
        }

        // フォームエディタに表示するフィールド名
        public function get_form_editor_field_title() {
            return esc_html__( 'ふりがな', 'gf_furigana_captcha' );
        }

        // フォームエディタのどのグループにボタンを配置するか
        public function get_form_editor_button() {
            return array(
                'group' => 'advanced_fields',
                'text'  => $this->get_form_editor_field_title(),
            );
        }

        // 利用可能なフィールド設定を指定
        public function get_form_editor_field_settings() {
            return array(
                'label_setting',
                'description_setting',
                'required_setting',
                'rules_setting',
                'conditional_logic_field_setting',
                'admin_label_setting',
                'input_class_setting',
                'css_class_setting',
                'placeholder_setting',
                'size_setting'
            );
        }

        // 必須項目チェックを修正：個別に値を取得
        public function is_value_submission_empty( $form_id ) {
            // 各入力項目の値を直接取得
            $value1 = rgpost( 'input_' . $this->id . '_1' );
            $value2 = rgpost( 'input_' . $this->id . '_2' );
            return empty( $value1 ) && empty( $value2 );
        }

        // 送信値取得処理を改善：個別に POST から値を取得
        public function get_value_submission( $field_values, $get_from_post_global_var = true ) {
            $value = parent::get_value_submission($field_values, $get_from_post_global_var);
            if (!is_array($value)) {
                $value = array();
            }
            return array(
                $this->id . '.1' => rgpost('input_' . $this->id . '_1'),
                $this->id . '.2' => rgpost('input_' . $this->id . '_2')
            );
        }

        // フロント側のフォームに出力される入力フィールドのHTMLを定義
        public function get_field_input( $form, $value = '', $entry = null ) {
            $form_id    = absint( $form['id'] );
            $field_id   = absint( $this->id );
            $frontend_id_prefix = "input_{$form_id}_{$field_id}";

            $sei_value = ''; 
            $mei_value = '';
            if ( is_array( $value ) ) {
                $sei_value = rgar( $value, $this->id . '.1' );
                $mei_value = rgar( $value, $this->id . '.2' );
            }

            $tabindex   = $this->get_tabindex();
            $css_class  = $this->size;

            $container_style = 'display: flex; gap: 10px; margin-bottom: 15px;';
            $input_style = 'flex: 1; min-width: 120px;';

            $sei_input = sprintf(
                '<span style="%s">
                    <input type="text" name="input_%d_1" id="%s_1" value="%s" %s class="%s" placeholder="せい" style="width: 100%%;" />
                </span>',
                $input_style,
                $field_id,
                $frontend_id_prefix,
                esc_attr( $sei_value ),
                $tabindex,
                esc_attr( $css_class )
            );

            $mei_input = sprintf(
                '<span style="%s">
                    <input type="text" name="input_%d_2" id="%s_2" value="%s" %s class="%s" placeholder="めい" style="width: 100%%;" />
                </span>',
                $input_style,
                $field_id,
                $frontend_id_prefix,
                esc_attr( $mei_value ),
                $tabindex,
                esc_attr( $css_class )
            );

            return sprintf('<span style="%s">%s%s</span>', $container_style, $sei_input, $mei_input);
        }

        // エントリー詳細画面での値の表示方法を定義
        public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
            if ( ! is_array( $value ) ) {
                return '';
            }

            $sei = rgar( $value, $this->id . '.1' );
            $mei = rgar( $value, $this->id . '.2' );

            return esc_html( $sei . ' ' . $mei );
        }

        // メール通知での値の表示方法を定義
        public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
            if ( ! is_array( $value ) ) {
                return '';
            }

            $sei = rgar( $value, $this->id . '.1' );
            $mei = rgar( $value, $this->id . '.2' );

            return $sei . ' ' . $mei;
        }
    }

    // 2. カスタムフィールドを登録
    GF_Fields::register( new GF_Field_Furigana() );

    // 3. バリデーションフィルターを登録
    add_filter( 'gform_field_validation', 'gf_furigana_field_validation', 10, 4 );
}

/**
 * ひらがなバリデーション処理
 */
function gf_furigana_field_validation( $result, $value, $form, $field ) {
    if ( $field->type === 'furigana' ) {
        $sei = rgar( $value, $field->id . '.1' );
        $mei = rgar( $value, $field->id . '.2' );

        // 必須チェック（両方必須）
        if ( $field->isRequired && ( empty( trim( $sei ) ) || empty( trim( $mei ) ) ) ) {
            $result['is_valid'] = false;
            $result['message']  = 'このフィールドは必須です。 次のフィールドに入力してください： せい, めい';
            return $result;
        }

        // ひらがなチェック（拡張版：句読点や長音対応）
        $hiragana_pattern = '/^[ぁ-ゔゞ゛゜ー〜、。]+$/u';
        $error = false;

        if ( !empty( $sei ) && !preg_match( $hiragana_pattern, $sei ) ) {
            $error = true;
        }
        if ( !empty( $mei ) && !preg_match( $hiragana_pattern, $mei ) ) {
            $error = true;
        }

        if ( $error ) {
            $result['is_valid'] = false;
            $result['message']  = 'ふりがなは「ひらがな」のみで入力してください';
        }
    }
    return $result;
}
