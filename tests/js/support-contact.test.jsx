import { render, screen } from '@testing-library/react';
import SupportContact from '../../src/analyze/components/support-contact';

describe( 'SupportContact', () => {
	it( 'renders nothing when contact is undefined', () => {
		const { container } = render( <SupportContact /> );
		expect( container.firstChild ).toBeNull();
	} );

	it( 'renders nothing when contact is an empty string', () => {
		const { container } = render( <SupportContact contact="" /> );
		expect( container.firstChild ).toBeNull();
	} );

	it( 'renders the contact description text', () => {
		render( <SupportContact contact="Contact WooCommerce support" /> );
		expect(
			screen.getByText( 'Contact WooCommerce support' )
		).toBeInTheDocument();
	} );

	it( 'renders a support link when contactUrl is provided', () => {
		render(
			<SupportContact
				contact="Contact us"
				contactUrl="https://woocommerce.com/support"
			/>
		);
		const link = screen.getByRole( 'link', { name: /visit support page/i } );
		expect( link ).toHaveAttribute( 'href', 'https://woocommerce.com/support' );
		expect( link ).toHaveAttribute( 'target', '_blank' );
	} );

	it( 'omits the support link when no contactUrl is provided', () => {
		render( <SupportContact contact="Contact us" /> );
		expect( screen.queryByRole( 'link' ) ).toBeNull();
	} );
} );
