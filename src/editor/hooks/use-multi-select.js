import { useCallback, useMemo } from '@wordpress/element';

import {
	normalizeIntegerList,
	normalizeStringList,
} from '../migrations/legacy-attributes';

function normalizeSelection( value, itemType ) {
	return itemType === 'integer'
		? normalizeIntegerList( value )
		: normalizeStringList( value );
}

/**
 * Connect a native multiple select to an array-valued block attribute.
 *
 * SelectControl exposes DOM values as strings, so integer ID attributes are
 * converted before setAttributes is called.
 *
 * @param {Array<string|number>} value         Current attribute value.
 * @param {string}               attributeName Attribute updated by selection.
 * @param {Function}             setAttributes Gutenberg update callback.
 * @param {'string'|'integer'}   itemType      Stored array item type.
 * @return {Object} Controlled selection value and change handler.
 */
export function useMultiSelect(
	value,
	attributeName,
	setAttributes,
	itemType = 'string'
) {
	const selectedValues = useMemo( () => {
		const normalizedValues = normalizeSelection( value, itemType ).map(
			String
		);

		return normalizedValues.length ? normalizedValues : [ '' ];
	}, [ itemType, value ] );
	const onChange = useCallback(
		( nextValues ) => {
			setAttributes( {
				[ attributeName ]: normalizeSelection( nextValues, itemType ),
			} );
		},
		[ attributeName, itemType, setAttributes ]
	);

	return { selectedValues, onChange };
}
