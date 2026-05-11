import { render, screen } from '@testing-library/react';
import FixSteps from '../../src/analyze/components/fix-steps';

describe( 'FixSteps', () => {
	it( 'renders nothing when steps is undefined', () => {
		const { container } = render( <FixSteps /> );
		expect( container.firstChild ).toBeNull();
	} );

	it( 'renders nothing when steps is an empty array', () => {
		const { container } = render( <FixSteps steps={ [] } /> );
		expect( container.firstChild ).toBeNull();
	} );

	it( 'renders a heading', () => {
		render( <FixSteps steps={ [ 'Do the thing' ] } /> );
		expect(
			screen.getByRole( 'heading', { name: /steps to fix/i } )
		).toBeInTheDocument();
	} );

	it( 'renders each step as a list item', () => {
		const steps = [ 'First step', 'Second step', 'Third step' ];
		render( <FixSteps steps={ steps } /> );

		const items = screen.getAllByRole( 'listitem' );
		expect( items ).toHaveLength( 3 );
		expect( items[ 0 ] ).toHaveTextContent( 'First step' );
		expect( items[ 1 ] ).toHaveTextContent( 'Second step' );
		expect( items[ 2 ] ).toHaveTextContent( 'Third step' );
	} );

	it( 'renders an ordered list', () => {
		render( <FixSteps steps={ [ 'Only step' ] } /> );
		expect( screen.getByRole( 'list' ).tagName ).toBe( 'OL' );
	} );
} );
