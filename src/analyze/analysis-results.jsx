/**
 * AnalysisResults — top-level component for the AI log analysis modal.
 *
 * Renders nothing when the modal is closed. When open it shows one of three
 * states: loading (Spinner), error (Notice), or results (full breakdown).
 */

import { Modal, Spinner, Notice, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useAnalyze from './hooks/use-analyze';
import SeverityNotice from './components/severity-notice';
import FixSteps from './components/fix-steps';
import SupportContact from './components/support-contact';

/**
 * Root analysis results component.
 *
 * @return {JSX.Element|null} The analysis modal, or null when closed.
 */
export default function AnalysisResults() {
	const {
		isOpen,
		isLoading,
		result,
		error,
		downloadError,
		close,
		downloadReport,
	} = useAnalyze();

	if ( ! isOpen ) {
		return null;
	}

	return (
		<Modal
			title={ __( 'AI Log Analysis', 'ai-log-analyzer' ) }
			onRequestClose={ close }
			size="large"
			className="ai-log-analyzer-modal"
		>
			{ /* Loading state */ }
			{ isLoading && (
				<div className="ai-log-analyzer-modal__loading">
					<Spinner />
					<p>
						{ window.aiLogAnalyzer?.i18n?.analyzing ??
							__( 'Analysing log\u2026', 'ai-log-analyzer' ) }
					</p>
				</div>
			) }

			{ /* Error state */ }
			{ ! isLoading && error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ /* Results state */ }
			{ ! isLoading && result && (
				<div className="ai-log-analyzer-modal__results">
					<SeverityNotice severity={ result.severity } />

					<div className="ai-log-analyzer-modal__section">
						<h3>{ __( 'Summary', 'ai-log-analyzer' ) }</h3>
						<p>{ result.summary }</p>
					</div>

					<div className="ai-log-analyzer-modal__section">
						<h3>{ __( 'Root Cause', 'ai-log-analyzer' ) }</h3>
						<p>{ result.cause }</p>
					</div>

					<FixSteps steps={ result.fix_steps } />

					<SupportContact
						contact={ result.contact }
						contactUrl={ result.contact_url }
					/>

					<div className="ai-log-analyzer-modal__actions">
						<Button variant="secondary" onClick={ downloadReport }>
							{ __( 'Download Report', 'ai-log-analyzer' ) }
						</Button>
						{ downloadError && (
							<Notice status="error" isDismissible={ false }>
								{ downloadError }
							</Notice>
						) }
					</div>
				</div>
			) }
		</Modal>
	);
}
