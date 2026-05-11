/**
 * SeverityNotice — maps an analysis severity level to a WordPress Notice status.
 */

import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/** @type {Object.<string, 'error'|'warning'|'info'>} */
const SEVERITY_STATUS_MAP = {
	critical: 'error',
	warning: 'warning',
	notice: 'info',
};

/**
 * Renders a Notice whose status reflects the analysis severity.
 *
 * @param {Object}                        props
 * @param {'critical'|'warning'|'notice'} props.severity
 * @return {JSX.Element} A Notice component styled to match the severity.
 */
export default function SeverityNotice( { severity } ) {
	const status = SEVERITY_STATUS_MAP[ severity ] ?? 'info';

	const labels = {
		critical: __(
			'Critical issue detected — immediate action required.',
			'ai-log-analyzer-for-woocommerce'
		),
		warning: __(
			'Warning — this issue may affect your store.',
			'ai-log-analyzer-for-woocommerce'
		),
		notice: __(
			'Notice — low-severity issue found.',
			'ai-log-analyzer-for-woocommerce'
		),
	};

	return (
		<Notice status={ status } isDismissible={ false }>
			{ labels[ severity ] ?? labels.notice }
		</Notice>
	);
}
