<?php

use GR\Wordpress\Core\Gr_Configuration;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<h2>GetResponse</h2>

<table class="form-table" role="presentation">
    <tr class="user-rich-editing-wrap">
        <th scope="row">Marketing Consent</th>
        <td>
            <label>
                <input type="hidden" name="<?php echo esc_html( Gr_Configuration::MARKETING_CONSENT_META_NAME ); ?>" value="0">
                <input name="<?php echo esc_html( Gr_Configuration::MARKETING_CONSENT_META_NAME ); ?>"
                                        <?php
										if ( esc_html( $is_gr_marketing_consent_checked ) ) {
											echo 'checked';  }
										?>
                 type="checkbox" id="<?php echo esc_html( Gr_Configuration::MARKETING_CONSENT_META_NAME ); ?>" value="1">
                <?php echo esc_html( $marketing_consent_text ); ?>
            </label>
        </td>
    </tr>
</table>

