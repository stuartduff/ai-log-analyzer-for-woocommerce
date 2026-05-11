import { renderHook, act } from '@testing-library/react';
import useAnalyze from '../../src/analyze/hooks/use-analyze';

const MOCK_RESULT = {
	severity: 'critical',
	summary: 'A fatal error occurred.',
	cause: 'Missing plugin dependency.',
	fix_steps: [ 'Install the dependency.', 'Reactivate the plugin.' ],
	contact: 'WooCommerce support',
	contact_url: 'https://woocommerce.com/support',
};

function fireAnalyzeEvent( fileId = 'test.log' ) {
	document.dispatchEvent(
		new CustomEvent( 'aiLogAnalyzer:analyze', {
			bubbles: true,
			detail: { fileId },
		} )
	);
}

function mockFetchSuccess( data ) {
	global.fetch = jest.fn( () =>
		Promise.resolve( {
			ok: true,
			json: () => Promise.resolve( data ),
			headers: { get: () => 'application/json' },
		} )
	);
}

function mockFetchBlob( blob, contentType = 'text/html' ) {
	global.fetch = jest.fn( () =>
		Promise.resolve( {
			ok: true,
			blob: () => Promise.resolve( blob ),
			headers: { get: () => contentType },
		} )
	);
}

beforeEach( () => {
	global.window.aiLogAnalyzer = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		i18n: { error: 'An error occurred during analysis.' },
	};
} );

afterEach( () => {
	jest.restoreAllMocks();
	delete global.window.aiLogAnalyzer;
	delete global.fetch;
} );

describe( 'useAnalyze — initial state', () => {
	it( 'starts closed with no data', () => {
		const { result } = renderHook( () => useAnalyze() );

		expect( result.current.isOpen ).toBe( false );
		expect( result.current.isLoading ).toBe( false );
		expect( result.current.result ).toBeNull();
		expect( result.current.error ).toBeNull();
		expect( result.current.fileId ).toBeNull();
		expect( result.current.downloadError ).toBeNull();
	} );
} );

describe( 'useAnalyze — aiLogAnalyzer:analyze event', () => {
	it( 'opens the modal and sets loading when the event fires', () => {
		global.fetch = jest.fn( () => new Promise( () => {} ) ); // never resolves
		const { result } = renderHook( () => useAnalyze() );

		act( () => {
			fireAnalyzeEvent( 'fatal.log' );
		} );

		expect( result.current.isOpen ).toBe( true );
		expect( result.current.isLoading ).toBe( true );
		expect( result.current.fileId ).toBe( 'fatal.log' );
		expect( result.current.result ).toBeNull();
		expect( result.current.error ).toBeNull();
	} );

	it( 'sets result and clears loading on a successful response', async () => {
		mockFetchSuccess( { success: true, data: MOCK_RESULT } );
		const { result } = renderHook( () => useAnalyze() );

		await act( async () => {
			fireAnalyzeEvent();
		} );

		expect( result.current.isLoading ).toBe( false );
		expect( result.current.result ).toEqual( MOCK_RESULT );
		expect( result.current.error ).toBeNull();
	} );

	it( 'sets error from response message when success is false', async () => {
		mockFetchSuccess( {
			success: false,
			data: { message: 'Analysis failed.' },
		} );
		const { result } = renderHook( () => useAnalyze() );

		await act( async () => {
			fireAnalyzeEvent();
		} );

		expect( result.current.error ).toBe( 'Analysis failed.' );
		expect( result.current.result ).toBeNull();
		expect( result.current.isLoading ).toBe( false );
	} );

	it( 'falls back to i18n error when response has no message', async () => {
		mockFetchSuccess( { success: false, data: {} } );
		const { result } = renderHook( () => useAnalyze() );

		await act( async () => {
			fireAnalyzeEvent();
		} );

		expect( result.current.error ).toBe( 'An error occurred during analysis.' );
	} );

	it( 'sets error on HTTP failure', async () => {
		global.fetch = jest.fn( () =>
			Promise.resolve( { ok: false, status: 500 } )
		);
		const { result } = renderHook( () => useAnalyze() );

		await act( async () => {
			fireAnalyzeEvent();
		} );

		expect( result.current.error ).toMatch( /HTTP 500/ );
		expect( result.current.isLoading ).toBe( false );
	} );

	it( 'sends the nonce and file_id in the request body', async () => {
		mockFetchSuccess( { success: true, data: MOCK_RESULT } );
		const { result: _result } = renderHook( () => useAnalyze() );

		await act( async () => {
			fireAnalyzeEvent( 'specific.log' );
		} );

		const [ , init ] = global.fetch.mock.calls[ 0 ];
		const body = new URLSearchParams( init.body );
		expect( body.get( 'action' ) ).toBe( 'ai_analyze_log' );
		expect( body.get( 'nonce' ) ).toBe( 'test-nonce' );
		expect( body.get( 'file_id' ) ).toBe( 'specific.log' );
	} );
} );

describe( 'useAnalyze — close()', () => {
	it( 'resets all state', async () => {
		mockFetchSuccess( { success: true, data: MOCK_RESULT } );
		const { result } = renderHook( () => useAnalyze() );

		await act( async () => {
			fireAnalyzeEvent();
		} );

		act( () => {
			result.current.close();
		} );

		expect( result.current.isOpen ).toBe( false );
		expect( result.current.result ).toBeNull();
		expect( result.current.error ).toBeNull();
		expect( result.current.fileId ).toBeNull();
	} );
} );

describe( 'useAnalyze — downloadReport()', () => {
	it( 'does nothing when there is no result', async () => {
		global.fetch = jest.fn();
		const { result } = renderHook( () => useAnalyze() );

		await act( async () => {
			result.current.downloadReport();
		} );

		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	it( 'triggers a file download on success', async () => {
		// First, obtain a result via the analyze event.
		mockFetchSuccess( { success: true, data: MOCK_RESULT } );
		const { result } = renderHook( () => useAnalyze() );

		await act( async () => {
			fireAnalyzeEvent();
		} );

		// Now mock the report download response as an HTML blob.
		const htmlBlob = new Blob( [ '<html>report</html>' ], {
			type: 'text/html',
		} );
		mockFetchBlob( htmlBlob, 'text/html' );

		// Stub URL and anchor APIs used by the download.
		const mockObjectUrl = 'blob:http://localhost/fake';
		URL.createObjectURL = jest.fn( () => mockObjectUrl );
		URL.revokeObjectURL = jest.fn();
		const clickSpy = jest.fn();
		jest.spyOn( document, 'createElement' ).mockImplementation( ( tag ) => {
			if ( tag === 'a' ) {
				return {
					href: '',
					download: '',
					click: clickSpy,
					remove: jest.fn(),
				};
			}
			return document.createElement( tag );
		} );
		jest.spyOn( document.body, 'appendChild' ).mockImplementation( () => {} );
		jest.spyOn( document.body, 'removeChild' ).mockImplementation( () => {} );

		await act( async () => {
			result.current.downloadReport();
		} );

		expect( clickSpy ).toHaveBeenCalledTimes( 1 );
		expect( URL.createObjectURL ).toHaveBeenCalledWith( htmlBlob );
		expect( URL.revokeObjectURL ).toHaveBeenCalledWith( mockObjectUrl );
	} );

	it( 'sets downloadError when the server returns a JSON error', async () => {
		mockFetchSuccess( { success: true, data: MOCK_RESULT } );
		const { result } = renderHook( () => useAnalyze() );

		await act( async () => {
			fireAnalyzeEvent();
		} );

		// Override fetch for the download call to return a JSON error.
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				ok: true,
				headers: { get: () => 'application/json' },
				json: () =>
					Promise.resolve( {
						data: { message: 'Report generation failed.' },
					} ),
			} )
		);

		await act( async () => {
			result.current.downloadReport();
		} );

		expect( result.current.downloadError ).toBe( 'Report generation failed.' );
	} );
} );
