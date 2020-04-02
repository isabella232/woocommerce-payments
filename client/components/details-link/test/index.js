/** @format */

/**
 * External dependencies
 */
import { shallow } from 'enzyme';

/**
 * Internal dependencies
 */
import DetailsLink from '../';

describe( 'Details link', () => {
	test( 'renders transaction details with charge ID', () => {
		const icon = shallow(
			<DetailsLink id="ch_mock" parentSegment="transactions">Content</DetailsLink>
		);
		expect( icon ).toMatchSnapshot();
	} );

	test( 'renders dispute details with ID', () => {
		const icon = shallow(
			<DetailsLink id="dp_mock" parentSegment="disputes">Content</DetailsLink>
		);
		expect( icon ).toMatchSnapshot();
	} );

	test( 'empty render with no ID', () => {
		const icon = shallow(
			<DetailsLink parentSegment="disputes">Content</DetailsLink>
		);
		expect( icon ).toMatchSnapshot();
	} );
} );
