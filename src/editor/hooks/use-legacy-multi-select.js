import { useCallback, useMemo } from '@wordpress/element';

export function parseLegacyMultiValue( value ) {
	if ( Array.isArray( value ) ) {
		return value.length ? value.map( String ) : [ '' ];
	}

	if ( value === undefined || value === null || value === '' ) {
		return [ '' ];
	}

	const selectedValues = String( value )
		.split( ',' )
		.map( ( item ) => item.trim() )
		.filter( Boolean );

	return selectedValues.length ? selectedValues : [ '' ];
}

/**
 * Keep the existing comma-separated block attribute contract until Stage 3.
 *
 * @param {*}        value         Current legacy attribute value.
 * @param {string}   attributeName Attribute updated by this selection.
 * @param {Function} setAttributes Gutenberg attribute update callback.
 * @return {Object} Controlled selection value and change handler.
 */
export function useLegacyMultiSelect( value, attributeName, setAttributes ) {
	const selectedValues = useMemo(
		() => parseLegacyMultiValue( value ),
		[ value ]
	);
	const onChange = useCallback(
		( nextValues ) => {
			setAttributes( {
				[ attributeName ]: Array.isArray( nextValues )
					? nextValues.join( ',' )
					: String( nextValues || '' ),
			} );
		},
		[ attributeName, setAttributes ]
	);

	return { selectedValues, onChange };
}
