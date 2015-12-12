<?php

class DP_AddThis_Admin {
	function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );

		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	function add_page() {
		$this->page = add_options_page( __('(dp) Addthis', 'dp-addthis'), __('(dp) Addthis', 'dp-addthis'), 'edit_plugins', 'dp-addthis', array(
			$this,
			'render_page'
		) );

		add_action( 'load-' . $this->page, array( $this, 'init_page' ) );
	}

	function init_page() {
		$this->add_meta_boxes();

		// Filter for 3rd plugins
		//do_action('add_meta_boxes', $this->page, null);
		do_action( 'add_meta_boxes_' . $this->page, null );

		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	function render_page() {
		?>
		<div class="wrap">

			<h1><?php echo esc_html( __('(dp) Addthis', 'dp-addthis') ); ?></h1>

			<form action="options.php" method="post">

				<?php
				settings_fields( 'dp_addthis' );
				do_settings_sections( 'dp-addthis' );
				$screen = get_current_screen();


				?>

				<?php
				wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
				wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>

				<div id="poststuff">

					<div id="post-body"
					     class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>">


						<div id="postbox-container-1" class="postbox-container">
							<?php do_meta_boxes( $screen, 'side', null ); ?>
						</div>

						<div id="postbox-container-2" class="postbox-container">
							<?php do_meta_boxes( $screen, 'normal', null ); ?>
							<?php do_meta_boxes( $screen, 'advanced', null ); ?>
						</div>


						<?php submit_button(); ?>

					</div> <!-- #post-body -->

				</div> <!-- #poststuff -->


			</form>

		</div><!-- .wrap -->

		<?php
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'admin', plugins_url( 'js/admin.js', dirname( __FILE__ ) ), array( 'postbox' ) );
	}

	function register_settings() {
		register_setting( 'dp_addthis', 'dp_addthis_smart_layers_display_rules', array( $this, 'sanitize_rules' ) );
		register_setting( 'dp_addthis', 'dp_addthis_inline_tools_display_rules', array( $this, 'sanitize_rules' ) );
		register_setting( 'dp_addthis', 'dp_addthis_config', array( $this, 'sanitize' ) );
		register_setting( 'dp_addthis', 'dp_addthis_share', array( $this, 'sanitize' ) );
		register_setting( 'dp_addthis', 'dp_addthis_pubid', array( $this, 'sanitize' ) );
		register_setting( 'dp_addthis', 'dp_addthis_custom_css', array( $this, 'sanitize' ) );
		register_setting( 'dp_addthis', 'dp_addthis_is_pro', array( $this, 'sanitize' ) );
	}

	function sanitize_rules( $rules ) {
		if ( is_array( $rules ) ) {
			// get unique values of a multidimensional array
			$rules = array_map( "unserialize", array_unique( array_map( "serialize", array_values( $rules ) ) ) );

			// unset pro tools if the user is not an AddThis pro.
			if(empty($_POST['dp_addthis_is_pro'])) {
				foreach($rules as $key => $rule) {
					$tool_key = $rule['tool'];
					$tools = dp_addthis_get_tools();
					if(!empty($tools[$tool_key]['is_pro'])) {
						unset($rules[$key]);
					}
				}
			}
		}

		$rules = is_array($rules) ? $rules : array();

		return $rules;
	}

	function sanitize( $value ) {
		return $value;
	}

	function add_meta_boxes() {
		$sections = array(
			'general'      => __( 'General', 'dp-addthis' ),
			'inline_tools' => __( 'Inline Tools', 'dp-addthis' ),
			'smart_layers' => __( 'Smart Layers', 'dp-addthis' ),
			'custom_css' => __( 'Custom CSS', 'dp-addthis' )
		);

		foreach ( $sections as $key => $label ) {
			add_meta_box( $key, $label, array( $this, 'section_' . $key ), $this->page, 'normal', 'default', null );
		}
	}

	function section_general() {
		$config_name  = 'dp_addthis_config';
		$config_value = wp_parse_args(get_option( 'dp_addthis_config', array()), array(
				'data_track_clickback' => false,
				'data_track_addressbar' => false,
				'data_ga_property' => ''
		) );

		?>

		<table class="form-table">
			<tbody>
			<tr>
				<th><a target="_blank" href="http://www.addthis.com/"><?php _e( 'Profile ID' ); ?></a></th>
				<td>
					<p><input type="text" name="dp_addthis_pubid" value="<?php echo get_option( 'dp_addthis_pubid' ); ?>"></p>
					<p>
					<?php
						$this->render_field(array(
							'type' => 'checkbox',
								'name' => 'dp_addthis_is_pro',
								'value' => get_option('dp_addthis_is_pro')
								));

					_e('Is this AddThis Pro?');
					?></p>
				</td>
			</tr>
			<tr>
				<th>
					<a target="_blank"
					   href="https://www.addthis.com/dashboard#analytics/ra-4ffef4ea5beb8912/all/14days"><?php _e( 'Analytics' ); ?></a>
				</th>
				<td>
					<p>
						<?php
						echo '<label>';
						$this->render_field( array(
							'type'  => 'checkbox',
							'name'  => $config_name . '[data_track_clickback]',
							'value' => $config_value['data_track_clickback']
						) );
						echo '<a target="_blank" href="http://www.addthis.com/academy/addthis-click-tracking/">' . __( 'Click Tracking' ) . '</a>';

						echo '</label>';
						?>
					</p>

					<p>
						<?php
						echo '<label>';
						$this->render_field( array(
							'type'  => 'checkbox',
							'name'  => $config_name . '[data_track_addressbar]',
							'value' => $config_value['data_track_addressbar']
						) );
						echo '<a target="_blank" href="http://www.addthis.com/academy/what-is-address-bar-sharing-analytics/">' . __( 'Address Bar Tracking' ) . '</a>';
						echo '</label>';
						?>
					</p>

					<p>
						<label><a target="_blank"
						          href="https://support.google.com/analytics/answer/1032385"><?php _e( 'Google Analytics Tracking ID' ); ?></a></label>

						<?php
						$this->render_field( array(
							'type'  => 'text',
							'name'  => $config_name . '[data_ga_property]',
							'value' => $config_value['data_ga_property']
						) );
						?>

					</p>

				</td>
			</tr>
			</tbody>
		</table>
	<?php }

	function section_inline_tools() { ?>
		<div class="items-container">
			<script type="text/html" class="tmpl-item">
				<?php $this->render_inline_tool( 'cloneindex' ); ?>
			</script>

			<table class="wp-list-table widefat striped">
				<thead>
				<tr>
					<th><?php _e( 'Tool', 'dp-addthis' ); ?></th>
					<th><?php _e( 'Context', 'dp-addthis' ); ?></th>
					<th><?php _e( 'Location', 'dp-addthis' ); ?></th>
					<th></th>
				</tr>
				</thead>
				<tbody>
				<?php
				$rules = dp_addthis_get_rules('inline_tools');
				if ( ! empty( $rules ) ) {
					foreach ( $rules as $index => $rule ) {
						$this->render_inline_tool( $index, $rule );
					}
				} ?>
				</tbody>
			</table>

			<p><button type="button" class="add-item button-primary"><?php _e( 'Add Display Rule', 'dp-addthis' ); ?></button></p>
		</div>
	<?php }

	function section_smart_layers() { ?>
		<div class="items-container">
			<script type="text/html" class="tmpl-item">
				<?php $this->render_smart_layer( 'cloneindex' ); ?>
			</script>

			<table class="rule-table widefat striped">
				<thead>
				<tr>
					<th><?php _e( 'Tool', 'dp-addthis' ); ?></th>
					<th><?php _e( 'Context', 'dp-addthis' ); ?></th>
					<th></th>
				</tr>
				</thead>
				<tbody>

				<?php
				$rules = dp_addthis_get_rules('smart_layers');
				if ( ! empty( $rules ) ) {
					foreach ( $rules as $index => $rule ) {
						$this->render_smart_layer( $index, $rule );
					}
				}
				?>
				</tbody>
			</table>
			<p><button type="button" class="add-item button-primary"><?php _e( 'Add Display Rule', 'dp-addthis' ); ?></button></p>
		</div>
	<?php }

	function section_custom_css() {
		echo '<textarea name="dp_addthis_custom_css" class="large-text code" rows="10" cols="50">'.esc_textarea(get_option('dp_addthis_custom_css', '')).'</textarea>';
	}

	function render_inline_tool( $index, $rule = '' ) {
		$basename = 'dp_addthis_inline_tools_display_rules[' . $index . ']';
		$is_pro = get_option('dp_addthis_is_pro');

		$rule = wp_parse_args( $rule, array(
			'tool'     => '',
			'context'  => '',
			'location' => ''
		) ); ?>
		<tr>
			<td>
				<?php
				// build choices
				$tool_args = array( 'is_smartlayer' => false );
				if(!$is_pro) {
					$tool_args['is_pro'] = false;
				}
				$tools   = dp_addthis_get_tools( $tool_args );
				$choices = array();
				foreach ( $tools as $key => $tool ) {
					$label = $tool['label'];
					if ( $tool['is_pro'] ) {
						$label .= ' (AddThis Pro)';
					}
					$choices[ $key ] = $label;
				}

				// render field
				$this->render_field( array(
					'type'    => 'select',
					'name'    => $basename . '[tool]',
					'choices' => $choices,
					'value'   => $rule['tool']
				) );
				?>
			</td>
			<td>
				<?php
				// build choices
				$contexts = dp_addthis_get_context_objects();
				$choices  = array();
				foreach ( $contexts as $key => $context ) {
					$choices[ $key ] = $context['label'];
				}

				// render field
				$this->render_field( array(
					'type'    => 'select',
					'name'    => $basename . '[context]',
					'choices' => $choices,
					'value'   => $rule['context']
				) );
				?>
			</td>
			<td>
				<?php
				// build choices
				$choices = array(
					'before_the_content' => __( 'Before Post Content', 'dp-addthis' ),
					'after_the_content'  => __( 'After Post Content', 'dp-addthis' ),
					'before_the_excerpt' => __( 'Before Post Excerpt', 'dp-addthis' ),
					'after_the_excerpt'  => __( 'After Post Excerpt', 'dp-addthis' )
				);

				// render field
				$this->render_field( array(
					'type'    => 'select',
					'name'    => $basename . '[location]',
					'choices' => $choices,
					'value'   => $rule['context']
				) );
				?>
			</td>
			<td class="delete">
				<button class="delete-item button">&times;</button>
			</td>
		</tr>
	<?php }

	function render_smart_layer( $index, $rule = '' ) {
		$basename = 'dp_addthis_smart_layers_display_rules[' . $index . ']';

		$is_pro = get_option('dp_addthis_is_pro');

		$rule = wp_parse_args( $rule, array(
			'tool'     => '',
			'context'  => '',
			'location' => ''
		) ); ?>
		<tr>
			<td>
				<?php
				// build choices
				$tool_args = array( 'is_smartlayer' => true );
				if(!$is_pro) {
					$tool_args['is_pro'] = false;
				}
				$tools   = dp_addthis_get_tools( $tool_args );
				$choices = array();
				foreach ( $tools as $key => $tool ) {
					$label = $tool['label'];
					if ( $tool['is_pro'] ) {
						$label .= ' (AddThis Pro)';
					}
					$choices[ $key ] = $label;
				}

				// render field
				$this->render_field( array(
					'type'    => 'select',
					'name'    => $basename . '[tool]',
					'choices' => $choices,
					'value'   => $rule['tool']
				) );
				?>
			</td>
			<td>
				<?php
				// build choices
				$contexts = dp_addthis_get_context_objects();
				$choices  = array();
				foreach ( $contexts as $key => $context ) {
					$choices[ $key ] = $context['label'];
				}

				// render field
				$this->render_field( array(
					'type'    => 'select',
					'name'    => $basename . '[context]',
					'choices' => $choices,
					'value'   => $rule['context']
				) );
				?>
			</td>
			<td class="delete">
				<button class="delete-item button">&times;</button>
			</td>
		</tr>
	<?php }

	function render_field( $args = '' ) {
		$args = wp_parse_args( $args, array(
			'type'    => 'text',
			'name'    => '',
			'value'   => '',
			'choices' => ''
		) );

		extract( $args );

		if ( $type == 'text' ) {
			echo '<input type="text" value="' . $value . '" name="' . $name . '" />';
		} elseif ( $type == 'checkbox' ) {
			echo '<input type="checkbox" value="1" name="' . $name . '"' . checked( $value, true, false ) . ' />';
		} elseif ( $type == 'select' ) {
			echo '<select name="' . $name . '">';
			foreach ( $choices as $choice => $label ) {
				echo '<option value="' . $choice . '"' . selected( $choice, $value, false ) . '>' . $label . '</option>';
			}
			echo '</select>';
		}
	}
}