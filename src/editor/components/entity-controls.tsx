import { Notice, SelectControl, Spinner } from '@wordpress/components';

import type { SelectOption } from '../hooks/use-entity-options';

interface LoadingStateProps {
	isLoading: boolean;
}

interface EntityMultiSelectControlProps {
	label: string;
	help?: string;
	value: string[];
	options: SelectOption[];
	onChange: ( value: string[] ) => void;
	isLoading?: boolean;
	emptyMessage?: string;
}

interface EntitySelectControlProps {
	label: string;
	help?: string;
	value: string | number;
	options: SelectOption[];
	onChange: ( value: string ) => void;
	isLoading?: boolean;
	emptyMessage?: string;
	disabled?: boolean;
}

function LoadingState( { isLoading }: LoadingStateProps ) {
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
}: EntityMultiSelectControlProps ) {
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
}: EntitySelectControlProps ) {
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
				value={ String( value ) }
				options={ options }
				onChange={ onChange }
				disabled={ disabled || isLoading || ! hasEntities }
			/>
		</>
	);
}
