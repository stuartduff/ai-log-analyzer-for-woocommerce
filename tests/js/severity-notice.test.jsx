import { render, screen } from '@testing-library/react';
import SeverityNotice from '../../src/analyze/components/severity-notice';

// @wordpress/components Notice mirrors its text into an aria-live region, so
// screen.getByText() would find two matches. Query within the render container
// to target only the visible notice content.

describe( 'SeverityNotice', () => {
	it( 'shows the critical label for critical severity', () => {
		const { container } = render( <SeverityNotice severity="critical" /> );
		expect( container ).toHaveTextContent( /critical issue detected/i );
	} );

	it( 'shows the warning label for warning severity', () => {
		const { container } = render( <SeverityNotice severity="warning" /> );
		expect( container ).toHaveTextContent( /this issue may affect your store/i );
	} );

	it( 'shows the notice label for notice severity', () => {
		const { container } = render( <SeverityNotice severity="notice" /> );
		expect( container ).toHaveTextContent( /low-severity issue found/i );
	} );

	it( 'falls back to the notice label for an unrecognised severity', () => {
		const { container } = render( <SeverityNotice severity="unknown" /> );
		expect( container ).toHaveTextContent( /low-severity issue found/i );
	} );
} );
