<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sutor_Lead_OS {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		Sutor_Lead_OS_DB::maybe_upgrade();
		Sutor_Lead_OS_Settings::init();
		Sutor_Lead_OS_REST::init();
		Sutor_Lead_OS_Frontend::init();

		if ( is_admin() ) {
			Sutor_Lead_OS_Admin::init();
			Sutor_Lead_OS_Leads_Page::init();
			Sutor_Lead_OS_Gtm_Page::init();
		}
	}

	public static function deactivate() {
		// Keep data; nothing to flush beyond rewrites.
		flush_rewrite_rules();
	}

	/**
	 * Default settings used on first install / reset.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'enabled'             => 1,
			'client_id'           => 'client',
			'form_id'             => 'main',
			'popup_title'         => '免費咨詢',
			'popup_subtitle'      => '回答幾條短問題，我哋會即刻喺 WhatsApp 同你跟進。',
			'fab_label'           => '免費咨詢',
			'cta_label'           => '傳送資料到 WhatsApp',
			'close_label'         => '關閉',
			'auto_show_fab'       => 1,
			'whatsapp_number'     => '',
			'whatsapp_template'   => "你好，我想咨詢。

{answers}

Lead ID: {lead_id}",
			'enable_gtm'          => 1,
			'supabase_url'        => '',
			'supabase_anon_key'   => '',
			'questions'           => array(
				array(
					'id'       => 'interest',
					'label'    => '你想咨詢咩？',
					'type'     => 'single',
					'required' => 1,
					'options'  => array(
						array( 'id' => 'service', 'label' => '服務詳情' ),
						array( 'id' => 'pricing', 'label' => '收費／方案' ),
						array( 'id' => 'booking', 'label' => '預約／申請' ),
						array( 'id' => 'other', 'label' => '其他' ),
					),
				),
				array(
					'id'       => 'name',
					'label'    => '點稱呼你？',
					'type'     => 'text',
					'required' => 1,
					'options'  => array(),
				),
				array(
					'id'       => 'phone',
					'label'    => 'WhatsApp／電話號碼',
					'type'     => 'phone',
					'required' => 1,
					'options'  => array(),
				),
			),
			'qualification_rules' => array(
				array(
					'id'           => 'rule_intent',
					'question_id'  => 'interest',
					'operator'     => 'in',
					'answer_ids'   => array( 'service', 'pricing', 'booking' ),
					'reason'       => 'intent',
					'reason_label' => 'Clear intent',
				),
			),
		);
	}
}
