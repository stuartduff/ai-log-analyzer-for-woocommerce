/**
 * FixSteps — numbered list of step-by-step fix instructions.
 */

import { __ } from '@wordpress/i18n';

/**
 * Renders an ordered list of fix steps from the analysis result.
 *
 * @param {Object}   props
 * @param {string[]} props.steps Array of fix-step strings.
 * @return {JSX.Element|null} Ordered list of fix steps, or null when empty.
 */
export default function FixSteps( { steps } ) {
	if ( ! steps?.length ) {
		return null;
	}

	return (
		<div className="ai-log-analyzer-fix-steps">
			<h3>{ __( 'Steps to Fix', 'ai-log-analyzer-for-woocommerce' ) }</h3>
			<ol>
				{ steps.map( ( step, index ) => (
					// Steps come from the AI — the index is a stable key here.
					// eslint-disable-next-line react/no-array-index-key
					<li key={ index }>{ step }</li>
				) ) }
			</ol>
		</div>
	);
}
