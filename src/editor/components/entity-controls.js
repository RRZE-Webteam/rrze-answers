import { Notice, SelectControl, Spinner } from '@wordpress/components';

function LoadingState( { isLoading } ) {
	return isLoading ? <Spinner /> : null;
}

export function EntityMultiSelectControl( {
	label,
	help,
	value,
	options,
	onChange,
	isLoading = false,
	emptyMessage,
} ) {
	const hasEntities = options.length > 1;

	return (
		<>
			<LoadingState isLoading={ isLoading } />
			{ ! isLoading && ! hasEntities && emptyMessage && (
				<Notice status="warning" isDismissible={ false }>
					{ emptyMessage }
				</Notice>
			) }
			<SelectControl
				label={ label }
				help={ help }
				value={ value }
				options={ options }
				onChange={ onChange }
				disabled={ isLoading || ! hasEntities }
				multiple
			/>
		</>
	);
}

export function EntitySelectControl( {
	label,
	help,
	value,
	options,
	onChange,
	isLoading = false,
	emptyMessage,
	disabled = false,
} ) {
	const hasEntities = options.length > 1;

	return (
		<>
			<LoadingState isLoading={ isLoading } />
			{ ! isLoading && ! hasEntities && emptyMessage && (
				<Notice status="warning" isDismissible={ false }>
					{ emptyMessage }
				</Notice>
			) }
			<SelectControl
				label={ label }
				help={ help }
				value={ value }
				options={ options }
				onChange={ onChange }
				disabled={ disabled || isLoading || ! hasEntities }
			/>
		</>
	);
}
