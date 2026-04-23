<?php

use GetResponse\WordPress\Core\Gr_Configuration;
use GetResponse\WordPress\Core\Gr_Nonce_Field;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<h2>GetResponse</h2>

<table class="form-table" role="presentation">
	<tr class="user-rich-editing-wrap">
		<th scope="row">Marketing Consent</th>
		<td>
			<label class="<?php echo esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_LABEL_CLASS ); ?>">
				<input type="hidden" name="<?php echo esc_html( Gr_Configuration::MARKETING_CONSENT_META_NAME ); ?>" value="0">
				<input name="<?php echo esc_html( Gr_Configuration::MARKETING_CONSENT_META_NAME ); ?>"
										<?php
										if ( esc_html( $is_gr_marketing_consent_checked ) ) {
											echo 'checked';  }
										?>
				type="checkbox" id="<?php echo esc_html( Gr_Configuration::MARKETING_CONSENT_META_NAME ); ?>" value="1"
				class="<?php echo esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_CHECKBOX_CLASS ); ?>">
				<?php echo esc_html( $marketing_consent_text ); ?>
			</label>

			<?php wp_nonce_field( Gr_Nonce_Field::ACTION_NAME, Gr_Nonce_Field::FIELD_NAME ); ?>
		</td>
	</tr>
</table>

