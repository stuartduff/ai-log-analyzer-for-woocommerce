/**
 * useAnalyze hook — manages analysis modal state and AJAX round-trip.
 *
 * Listens for the `aiLogAnalyzer:analyze` custom event (dispatched by the
 * MutationObserver button injector in index.js) and drives the modal lifecycle.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Maximum milliseconds to wait for an analysis response before timing out.
 * AI calls can be slow; 120 s gives sufficient headroom.
 *
 * @type {number}
 */
const ANALYZE_TIMEOUT_MS = 120_000;

/**
 * Maximum milliseconds to wait for a report download response.
 *
 * @type {number}
 */
const DOWNLOAD_TIMEOUT_MS = 30_000;

/**
 * Sends a form-encoded POST to the WordPress AJAX endpoint.
 *
 * @param {string}      action   WordPress AJAX action name.
 * @param {Object}      payload  Key/value pairs to include in the POST body.
 * @param {AbortSignal} [signal] Optional AbortSignal for timeout / cancellation.
 * @return {Promise<Response>} Raw fetch Response (caller decides how to read it).
 */
async function wpAjaxRaw( action, payload, signal ) {
	const body = new URLSearchParams( {
		action,
		nonce: window.aiLogAnalyzer?.nonce ?? '',
		...payload,
	} );

	const response = await fetch(
		window.aiLogAnalyzer?.ajaxUrl ?? '/wp-admin/admin-ajax.php',
		{
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
			credentials: 'same-origin',
			signal,
		}
	);

	if ( ! response.ok ) {
		throw new Error( `HTTP ${ response.status }` );
	}

	return response;
}

/**
 * Hook that drives the AI log analysis modal.
 *
 * State:
 *   isOpen    — whether the modal is visible
 *   isLoading — whether an AJAX call is in-flight
 *   result    — decoded analysis result object (null until success)
 *   error     — error message string (null unless a failure occurred)
 *   fileId    — the file identifier being analysed
 *
 * @return {{
 *   isOpen: boolean,
 *   isLoading: boolean,
 *   result: Object|null,
 *   error: string|null,
 *   fileId: string|null,
 *   close: Function,
 *   downloadReport: Function
 * }} Analysis modal state and control functions.
 */
export default function useAnalyze() {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ fileId, setFileId ] = useState( null );
	const [ downloadError, setDownloadError ] = useState( null );

	const handleAnalyze = useCallback( ( event ) => {
		const id = event?.detail?.fileId ?? null;

		setFileId( id );
		setIsOpen( true );
		setIsLoading( true );
		setResult( null );
		setError( null );

		const controller = new AbortController();
		const timeoutId = setTimeout(
			() => controller.abort(),
			ANALYZE_TIMEOUT_MS
		);

		wpAjaxRaw( 'ai_analyze_log', { file_id: id }, controller.signal )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				if ( response?.success ) {
					setResult( response.data );
				} else {
					setError(
						response?.data?.message ??
							window.aiLogAnalyzer?.i18n?.error ??
							'An error occurred during analysis.'
					);
				}
			} )
			.catch( ( err ) => {
				const isTimeout =
					err?.name === 'AbortError' ||
					err?.message?.includes( 'aborted' );

				setError(
					isTimeout
						? __(
								'The analysis timed out. The log file may be too large or the AI service may be unavailable.',
								'ai-log-analyzer'
						  )
						: err?.message ??
								window.aiLogAnalyzer?.i18n?.error ??
								'An error occurred during analysis.'
				);
			} )
			.finally( () => {
				clearTimeout( timeoutId );
				setIsLoading( false );
			} );
	}, [] );

	useEffect( () => {
		document.addEventListener( 'aiLogAnalyzer:analyze', handleAnalyze );
		return () => {
			document.removeEventListener(
				'aiLogAnalyzer:analyze',
				handleAnalyze
			);
		};
	}, [ handleAnalyze ] );

	/**
	 * Closes the modal and resets state.
	 */
	const close = useCallback( () => {
		setIsOpen( false );
		setResult( null );
		setError( null );
		setFileId( null );
		setDownloadError( null );
	}, [] );

	/**
	 * Triggers an HTML report download for the current analysis result.
	 *
	 * The PHP handler streams an HTML file directly (Content-Disposition:
	 * attachment). We fetch it as a Blob, create a temporary object URL, and
	 * click a hidden anchor to trigger the native browser download prompt.
	 *
	 * @return {Promise<void>}
	 */
	const downloadReport = useCallback( async () => {
		if ( ! result ) {
			return;
		}

		setDownloadError( null );

		const controller = new AbortController();
		// eslint-disable-next-line @wordpress/no-unused-vars-before-return
		const timeoutId = setTimeout(
			() => controller.abort(),
			DOWNLOAD_TIMEOUT_MS
		);

		try {
			const response = await wpAjaxRaw(
				'ai_download_report',
				{ analysis_data: JSON.stringify( result ) },
				controller.signal
			);

			const contentType = response.headers.get( 'Content-Type' ) ?? '';

			// If the server returned JSON the handler encountered an error.
			if ( contentType.includes( 'application/json' ) ) {
				const data = await response.json();
				setDownloadError(
					data?.data?.message ??
						__( 'Report download failed.', 'ai-log-analyzer' )
				);
				return;
			}

			// Stream the HTML report as a downloadable file.
			const blob = await response.blob();
			const url = URL.createObjectURL( blob );
			const link = document.createElement( 'a' );
			link.href = url;
			link.download = 'wc-log-analysis-report.html';
			document.body.appendChild( link );
			link.click();
			document.body.removeChild( link );
			URL.revokeObjectURL( url );
		} catch ( err ) {
			setDownloadError(
				err?.name === 'AbortError'
					? __(
							'Report download timed out. Please try again.',
							'ai-log-analyzer'
					  )
					: __(
							'Report download failed. Please try again.',
							'ai-log-analyzer'
					  )
			);
		} finally {
			clearTimeout( timeoutId );
		}
	}, [ result ] );

	return {
		isOpen,
		isLoading,
		result,
		error,
		fileId,
		downloadError,
		close,
		downloadReport,
	};
}
