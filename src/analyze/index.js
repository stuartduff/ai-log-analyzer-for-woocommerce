/**
 * Analyze app entry point.
 *
 * Responsibilities:
 *  1. Mounts <AnalysisResults> into #ai-log-analyzer-results (modal overlay).
 *  2. Uses MutationObserver to inject "Analyse with AI" buttons into the WC
 *     log viewer UI whenever the DOM changes (handles React-rendered and
 *     server-rendered log views).
 *
 * Selector strategy (confirmed against WooCommerce source):
 *  - FileV2 single_file view : .wc-logs-single-file-actions
 *    file_id source           : URL ?file_id=
 *  - Legacy File handler      : #log-viewer-select .alignleft
 *    file_id source           : URL ?log_file=
 *  - DB handler               : no single-file concept; button omitted
 *    (DB logs are a paginated table; analysing by source is a future UX)
 */

import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import AnalysisResults from './analysis-results';
import './index.scss';

// ---------------------------------------------------------------------------
// Selector constants — keep all selector strings in one place.
// ---------------------------------------------------------------------------

const SELECTOR_FILEV2_ACTIONS = '.wc-logs-single-file-actions';
const SELECTOR_LEGACY_ACTIONS = '#log-viewer-select .alignleft';
const BUTTON_CLASS = 'ai-log-analyzer-btn';

// ---------------------------------------------------------------------------
// file_id resolution
// ---------------------------------------------------------------------------

/**
 * Returns the current log file identifier from the URL, or null if none.
 *
 * @return {string|null} The log file identifier, or null if not present.
 */
function getFileId() {
	const params = new URLSearchParams( window.location.search );
	const view = params.get( 'view' );

	// FileV2 single_file view.
	if ( 'single_file' === view && params.get( 'file_id' ) ) {
		return params.get( 'file_id' );
	}

	// Legacy File handler — uses ?log_file= param.
	const logFile = params.get( 'log_file' );
	if ( logFile ) {
		return logFile;
	}

	return null;
}

// ---------------------------------------------------------------------------
// Button injection
// ---------------------------------------------------------------------------

/**
 * Creates and returns the "Analyse with AI" button element.
 *
 * @param {string} fileId The log file identifier to pass in the custom event.
 * @return {HTMLButtonElement} The configured button element.
 */
function createAnalyzeButton( fileId ) {
	const button = document.createElement( 'button' );

	button.type = 'button';
	button.className = `button button-secondary ${ BUTTON_CLASS }`;
	button.textContent =
		window.ailwcLogAnalyzer?.i18n?.analyzeButton ?? 'Analyse with AI';

	button.addEventListener( 'click', () => {
		document.dispatchEvent(
			new CustomEvent( 'ailwcLogAnalyzer:analyze', {
				bubbles: true,
				detail: { fileId },
			} )
		);
	} );

	return button;
}

/**
 * Attempts to inject the "Analyse with AI" button into the correct container.
 *
 * Only injects if a file_id is available and the button has not already been
 * added (idempotent — safe to call on every MutationObserver tick).
 */
function tryInjectButton() {
	const fileId = getFileId();

	// No identifiable log in view — nothing to inject.
	if ( ! fileId ) {
		return;
	}

	// FileV2: inject into .wc-logs-single-file-actions.
	const fileV2Container = document.querySelector( SELECTOR_FILEV2_ACTIONS );

	if (
		fileV2Container &&
		! fileV2Container.querySelector( `.${ BUTTON_CLASS }` )
	) {
		fileV2Container.appendChild( createAnalyzeButton( fileId ) );
		return;
	}

	// Legacy File handler: inject into #log-viewer-select .alignleft.
	const legacyContainer = document.querySelector( SELECTOR_LEGACY_ACTIONS );

	if (
		legacyContainer &&
		! legacyContainer.querySelector( `.${ BUTTON_CLASS }` )
	) {
		legacyContainer.appendChild( createAnalyzeButton( fileId ) );
	}
}

// ---------------------------------------------------------------------------
// MutationObserver — watches for dynamic DOM updates (e.g. React-rendered
// log viewer content or AJAX tab loads).
// ---------------------------------------------------------------------------

// eslint-disable-next-line no-undef
const observer = new MutationObserver( () => {
	tryInjectButton();
} );

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------

domReady( () => {
	// Mount the React modal overlay.
	const container = document.getElementById( 'ai-log-analyzer-results' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <AnalysisResults /> );
	}

	// Attempt button injection for statically-rendered content.
	tryInjectButton();

	// Watch for dynamic DOM updates.
	observer.observe( document.body, {
		childList: true,
		subtree: true,
	} );
} );
