<?php

/*
 * Plugin Name: basic_qr
 * Plugin URI: https://seventy9.co.uk/wp-plugins
 * Description: a qr code generation API plugin
 * Author: Scott A. Dixon
 * Author URI: https://seventy9.co.uk
 * Version: 1.0.0
 */

defined('ABSPATH') or die('⎺\_(ツ)_/⎺');

// defines        

define('_PLUGIN_BQR', 'basic_qr');

define('_URL_BQR', plugin_dir_url(__FILE__));
define('_PATH_BQR', plugin_dir_path(__FILE__));

// admin fields

define('_ARGS_BQR', [
	'bqr_active' => [
		'type' => 'string',
		'default' => 'yes'
	],
	'bqr_keys' => [
		'type' => 'string',
		'default' => ''
	]
]);

// admin form

define('_ADMIN_BQR', [
	'general' => [
		'label' => 'General',
		'columns' => 4,
		'fields' => [
			'bqr_active' => [
				'label' => 'API Active',
				'type' => 'check'
			],
			'bqr_keys' => [
				'label' => 'API Keys',
				'type' => 'text'
			]
		]
	]
]);

//   ▄████████   ▄█           ▄████████     ▄████████     ▄████████  
//  ███    ███  ███          ███    ███    ███    ███    ███    ███  
//  ███    █▀   ███          ███    ███    ███    █▀     ███    █▀   
//  ███         ███          ███    ███    ███           ███         
//  ███         ███        ▀███████████  ▀███████████  ▀███████████  
//  ███    █▄   ███          ███    ███           ███           ███  
//  ███    ███  ███▌    ▄    ███    ███     ▄█    ███     ▄█    ███  
//  ████████▀   █████▄▄██    ███    █▀    ▄████████▀    ▄████████▀  

class bqr_API {
	public function add_routes() {
		register_rest_route(_PLUGIN_BQR . '-api', '/settings', [
				'methods' => 'POST',
				'callback' => [$this, 'update_settings'],
				'args' => bqr_Settings::args(),
				'permission_callback' => [$this, 'permissions']
			]
		);
		register_rest_route(_PLUGIN_BQR . '-api', '/settings', [
				'methods' => 'GET',
				'callback' => [$this, 'get_settings'],
				'args' => [],
				'permission_callback' => [$this, 'permissions']
			]
		);
		register_rest_route(_PLUGIN_BQR . '-api', '/generate', [
				'methods' => 'GET',
				'callback' => [$this, 'get_qrcode'],
				'args' => [],
				'permission_callback' => '__return_true'
			]
		);
	}

	public function permissions() {
		return current_user_can('manage_options');
	}

	public function update_settings(WP_REST_Request $request) {
		$settings = [];
		foreach (bqr_Settings::args() as $key => $val) {
			$settings[$key] = $request->get_param($key);
		}
		bqr_Settings::save_settings($settings);
		return rest_ensure_response(bqr_Settings::get_settings());
	}

	public function get_settings(WP_REST_Request $request) {
		return rest_ensure_response(bqr_Settings::get_settings());
	}

	public function get_qrcode(WP_REST_Request $request) {
		if (_BQRP['bqr_active'] == 'yes') {
			$key = $request->get_param('key');
			$keys = explode("\n", str_replace(["\r\n","\n\r","\r"], "\n", _BQRP['bqr_keys']));

			if ($key) {
				if (in_array($key, $keys)) {
					$format = $request->get_param('format');
					$value = $request->get_param('value');

					if ($value) {
						require_once(_PATH_BQR . 'lib/qrlib.php');

						switch ($format) {
							case 'png': {
								QRcode::png($value);
								break;
							}
							case 'svg': {
								header('Content-type: image/svg+xml');
								echo QRcode::svg($value);
								break;
							}
							default: {
								return rest_ensure_response(['error' => 'invalid format']);
							}
						}			
					}
					else {
						return rest_ensure_response(['error' => 'no value data']);
					}
				}
				else {
					return rest_ensure_response(['error' => 'invalid api key']);
				}			
			}
			else {
				return rest_ensure_response(['error' => 'no api key']);
			}
		}
		else {
			return rest_ensure_response(['error' => 'api is disabled']);
		}
	}
}

class bqr_Settings {
	protected static $option_key = _PLUGIN_BQR . '-settings';

	public static function args() {
		$args = _ARGS_BQR;
		foreach (_ARGS_BQR as $key => $val) {
			$val['required'] = true;
			switch ($val['type']) {
				case 'integer': {
					$cb = 'absint';
					break;
				}
				default: {
					$cb = 'sanitize_text_field';
				}
				$val['sanitize_callback'] = $cb;
			}
		}
		return $args;
	}

	public static function get_settings() {
		$defaults = [];
		foreach (_ARGS_BQR as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		$saved = get_option(self::$option_key, []);
		if (!is_array($saved) || empty($saved)) {
			return $defaults;
		}
		return wp_parse_args($saved, $defaults);
	}

	public static function save_settings(array $settings) {
		$defaults = [];
		foreach (_ARGS_BQR as $key => $val) {
			$defaults[$key] = $val['default'];
		}
		foreach ($settings as $i => $setting) {
			if (!array_key_exists($i, $defaults)) {
				unset($settings[$i]);
			}
		}
		update_option(self::$option_key, $settings);
	}
}

class bqr_Menu {
	protected $slug = _PLUGIN_BQR . '-menu';
	protected $assets_url;

	public function __construct($assets_url) {
		$this->assets_url = $assets_url;
		add_action('admin_menu', [$this, 'add_page']);
		add_action('admin_enqueue_scripts', [$this, 'register_assets']);
	}

	public function add_page() {
		add_menu_page(
			_PLUGIN_BQR,
			_PLUGIN_BQR,
			'manage_options',
			$this->slug,
			[$this, 'render_admin'],
			'data:image/svg+xml;base64,' . base64_encode(
				'<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="500px" height="500px" viewbox="0 0 500 500"><path fill="#a7aaad" d="M239.4,472h21.1v-42.3h21.1v-21.1h-42.3L239.4,472 M281.7,408.6h21.1v-42.3h-42.3V324h-21.1v21.1h-21.1v-42.3h-21.1v84.6 h84.6v21.2 M91.4,302.9h21.1v-21.1h21.1v-21.1h-21.1v-21.1H70.3v-21.1H49.1v21.1H28v21.1h42.3v21.1h21.1V302.9 M239.4,302.9h63.4 v-21.1h-21.1v-42.3h-21.1v42.3h-21.1L239.4,302.9 M324,302.9h63.4v-21.1h21.1v-21.1h21.1v-21.1h-21.1V176h-21.1v21.1h-21.1v21.1 h21.1v42.3h-21.1v21.1H324V302.9 M408.6,302.9h42.3v-21.1H472v-42.3h-21.1v21.1h-21.1v21.1h-21.1L408.6,302.9 M239.4,197.1h21.1V176 h42.3v-21.1H197.1v42.3h21.1V176h21.1L239.4,197.1 M429.7,176h21.1v-63.4H472V70.3h-21.1v21.1h-21.1V70.3H302.9v42.3h-21.1v21.1H324 V176h-21.1v21.1H324v21.1h-42.3v21.1h21.1v42.3H324v-42.3h21.1V176h42.3v-63.4h-42.3V91.4h63.4v21.1h21.1V176 M345.1,154.9v-21.1 h21.1v21.1H345.1 M218.3,133.7h42.3V91.4h21.1V70.3h21.1V49.1h-42.3v21.1h-42.3v21.1h-21.1v21.1h21.1L218.3,133.7 M281.7,345.1V324 h21.1v21.1H281.7 M218.3,281.7v-21.1h21.1v21.1H218.3 M154.9,260.6v-21.1H176v21.1H154.9 M197.1,260.6v-21.1h21.1v21.1H197.1 M345.1,260.6v-21.1h21.1v21.1H345.1 M112.6,239.4v-21.1h21.1v21.1H112.6 M239.4,239.4v-21.1h21.1v21.1H239.4 M429.7,239.4v-21.1 h21.1v21.1H429.7 M28,218.3v-21.1h21.1v21.1H28 M70.3,218.3v-21.1h21.1v21.1H70.3 M218.3,218.3v-21.1h21.1v21.1H218.3 M260.6,218.3 v-21.1h21.1v21.1H260.6 M302.9,49.1V28H324v21.1H302.9 M429.7,49.1V28h21.1v21.1H429.7 M281.7,472h21.1v-42.3h-21.1V472 M197.1,450.9h21.1v-42.3h-21.1V450.9 M28,302.9h42.3v-21.1H28V302.9 M154.9,218.3h42.3v-21.1h-42.3V218.3 M450.9,218.3H472V176 h-21.1V218.3 M197.1,70.3h21.1V28h-21.1V70.3 M366.3,49.1h42.3V28h-42.3V49.1 M154.9,302.9h42.3v-42.3H176v21.1h-21.1V302.9z"/><path fill="#a7aaad" d="M28,28v150.6h150.6V28H28z M49.5,49.5h107.6v107.6H49.5V49.5z M71,71v64.6h64.6V71H71z"/><path fill="#a7aaad" d="M28,321.4V472h150.6V321.4H28z M49.5,342.9h107.6v107.6H49.5V342.9z M71,364.4V429h64.6v-64.6H71z"/><path fill="#a7aaad" d="M321.4,321.4V472H472V321.4H321.4z M342.9,342.9h107.6v107.6H342.9V342.9z M364.4,364.4V429H429v-64.6H364.4z"/></svg>'
			),
			30
		);
	}

	public function register_assets() {
		$boo = microtime(false);

		wp_register_script($this->slug, $this->assets_url . '/' . _PLUGIN_BQR . '.js?' . $boo, ['jquery']);
		wp_register_style($this->slug, $this->assets_url . '/' . _PLUGIN_BQR . '.css?' . $boo);

		wp_localize_script($this->slug, _PLUGIN_BQR, [
			'strings' => [
				'saved' => 'Settings Saved',
				'error' => 'Error'
			],
			'api' => [
				'url' => esc_url_raw(rest_url(_PLUGIN_BQR . '-api/settings')),
				'nonce' => wp_create_nonce('wp_rest')
			]
		]);
	}

	public function enqueue_assets() {
		if (!wp_script_is($this->slug, 'registered')) {
			$this->register_assets();
		}

		wp_enqueue_script($this->slug);
		wp_enqueue_style($this->slug);
	}

	public function render_admin() {
		wp_enqueue_media();
		$this->enqueue_assets();

		$name = _PLUGIN_BQR;
		$form = _ADMIN_BQR;
		$opts = null;

		// build form

		echo '<div id="' . $name . '-wrap" class="wrap">';
			echo '<h1>QR Code Generator API</h1>';
			echo '<p>Configure your settings...</p>';
			echo '<form id="' . $name . '-form" method="post">';
				echo '<nav id="' . $name . '-nav" class="nav-tab-wrapper">';
				foreach ($form as $tid => $tab) {
					echo '<a href="#' . $name . '-' . $tid . '" class="nav-tab">' . $tab['label'] . '</a>';
				}
				echo '</nav>';
				echo '<div class="tab-content">';
				foreach ($form as $tid => $tab) {
					echo '<div id="' . $name . '-' . $tid . '" class="' . $name . '-tab">';
					foreach ($tab['fields'] as $fid => $field) {
						echo '<div class="form-block col-' . $tab['columns'] . '">';
						switch ($field['type']) {
							case 'input': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								break;
							}
							case 'select': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<select id="' . $fid . '" name="' . $fid . '">';
									foreach ($field['values'] as $value => $label) {
										echo '<option value="' . $value . '">' . $label . '</option>';
									}
								echo '</select>';
								break;
							}
							case 'text': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<textarea id="' . $fid . '" class="tabs" name="' . $fid . '"></textarea>';
								break;
							}
							case 'file': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								echo '<input data-id="' . $fid . '" type="button" class="button-primary choose-file-button" value="...">';
								break;
							}
							case 'colour': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<input id="' . $fid . '" type="text" name="' . $fid . '">';
								echo '<input data-id="' . $fid . '" type="color" class="choose-colour-button" value="#000000">';
								break;
							}
							case 'code': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<textarea id="' . $fid . '" class="code" name="' . $fid . '"></textarea>';
								break;
							}
							case 'check': {
								echo '<em>' . $field['label'] . ':</em>';
								echo '<label class="switch">';
									echo '<input type="checkbox" id="' . $fid . '" name="' . $fid . '" value="yes">';
									echo '<span class="slider"></span>';
								echo '</label>';
								break;
							}
							case 'font': {
								echo '<label for="' . $fid . '">';
									echo $field['label'] . ':';
								echo '</label>';
								echo '<select id="' . $fid . '" name="' . $fid . '">';
									echo $opts;
								echo '</select>';
								break;
							}
						}
						echo '</div>';
					}
					echo '</div>';
				}
				echo '</div>';
				echo '<div>';
					submit_button();
				echo '</div>';
				echo '<div id="' . $name . '-feedback"></div>';
			echo '</form>';
		echo '</div>';
	}
}

// init admin

function bqr_init($dir) {
	if (is_admin()) {
		new bqr_Menu(_URL_BQR);
	}
}

// init api

function bqr_api_init() {
	bqr_Settings::args();
	$api = new bqr_API();
	$api->add_routes();
}

//   ▄█   ███▄▄▄▄▄     ▄█       ███      
//  ███   ███▀▀▀▀██▄  ███   ▀█████████▄  
//  ███▌  ███    ███  ███▌     ▀███▀▀██  
//  ███▌  ███    ███  ███▌      ███   ▀  
//  ███▌  ███    ███  ███▌      ███      
//  ███   ███    ███  ███       ███      
//  ███   ███    ███  ███       ███      
//  █▀     ▀█    █▀   █▀       ▄████▀    

define('_BQRP', bqr_Settings::get_settings());

// boot plugin

add_action('init', 'bqr_init');
add_action('rest_api_init', 'bqr_api_init');

// eof