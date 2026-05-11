/**
 * SupportContact — support contact information and link rendered below fix steps.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Renders the support contact text and an optional link to the plugin support URL.
 *
 * Passing `href` to `@wordpress/components` `Button` renders it as an `<a>` tag;
 * `target` and `rel` are forwarded automatically.
 *
 * @param {Object} props
 * @param {string} props.contact      Human-readable support contact description.
 * @param {string} [props.contactUrl] Optional support URL.
 * @return {JSX.Element|null} Support contact block, or null when contact is empty.
 */
export default function SupportContact( { contact, contactUrl } ) {
	if ( ! contact ) {
		return null;
	}

	return (
		<div className="ai-log-analyzer-support-contact">
			<h3>
				{ __( 'Who to Contact', 'ai-log-analyzer-for-woocommerce' ) }
			</h3>
			<p>{ contact }</p>
			{ contactUrl && (
				<Button
					variant="secondary"
					href={ contactUrl }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __(
						'Visit Support Page',
						'ai-log-analyzer-for-woocommerce'
					) }
				</Button>
			) }
		</div>
	);
}
