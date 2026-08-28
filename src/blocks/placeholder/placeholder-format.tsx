import apiFetch from '@wordpress/api-fetch';
import {
	RichTextShortcut,
	RichTextToolbarButton,
} from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	Flex,
	FlexItem,
	Notice,
	Popover,
	Spinner,
} from '@wordpress/components';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { applyFormat, insert, registerFormatType } from '@wordpress/rich-text';

import type { RichTextFormatEditProps } from '@wordpress/block-editor';

const FORMAT_NAME = 'rrze/placeholder';
const CLASS_NAME = 'rrze-placeholder';

interface PlaceholderRecord {
	id: number;
	title?: {
		rendered?: string;
	};
	content?: {
		rendered?: string;
	};
	titleLang?: string;
	meta?: {
		titleLang?: string;
	};
}

interface PlaceholderOption {
	value: string;
	label: string;
	long: string;
	lang: string;
}

interface FormatRegistration {
	title: string;
	tagName: string;
	className: string;
	attributes: Record< string, string >;
	edit: ( props: RichTextFormatEditProps ) => JSX.Element;
}

function asRichTextFormat(
	attributes: Record< string, string >
): Parameters< typeof applyFormat >[ 1 ] {
	return {
		type: FORMAT_NAME,
		attributes,
	} as unknown as Parameters< typeof applyFormat >[ 1 ];
}

function PlaceholderUI( {
	value,
	onChange,
	isActive,
}: RichTextFormatEditProps ) {
	const [ items, setItems ] = useState< PlaceholderRecord[] >( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState< Error | null >( null );
	const [ isOpen, setIsOpen ] = useState( false );
	const [ selectedId, setSelectedId ] = useState( '' );
	const anchorRef = useRef< HTMLSpanElement | null >( null );

	useEffect( () => {
		let cancelled = false;

		async function loadAll() {
			setLoading( true );
			setError( null );
			const perPage = 100;
			let page = 1;
			const records: PlaceholderRecord[] = [];

			try {
				while ( true ) {
					const batch = await apiFetch< PlaceholderRecord[] >( {
						path: `/wp/v2/placeholder?status=publish&per_page=${ perPage }&page=${ page }&orderby=title&order=asc&_fields=id,title,content,titleLang,meta`,
					} );
					if ( cancelled ) {
						return;
					}

					records.push( ...batch );
					if ( batch.length < perPage ) {
						break;
					}
					page += 1;
				}
				setItems( records );
			} catch ( fetchError ) {
				if ( ! cancelled ) {
					setError(
						fetchError instanceof Error
							? fetchError
							: new Error( String( fetchError ) )
					);
				}
			} finally {
				if ( ! cancelled ) {
					setLoading( false );
				}
			}
		}

		void loadAll();
		return () => {
			cancelled = true;
		};
	}, [] );

	const options = useMemo< PlaceholderOption[] >(
		() =>
			items.map( ( post ) => ( {
				value: String( post.id ),
				label:
					post.title?.rendered || __( '(no title)', 'rrze-answers' ),
				long:
					post.content?.rendered ||
					__( '(no content)', 'rrze-answers' ),
				lang: post.titleLang ?? post.meta?.titleLang ?? '',
			} ) ),
		[ items ]
	);

	const applyFromSelected = () => {
		if ( ! selectedId ) {
			return;
		}

		const picked = options.find(
			( option ) => option.value === selectedId
		);
		if ( ! picked ) {
			return;
		}

		const formatAttributes: Record< string, string > = {
			'data-placeholder-id': selectedId,
		};
		if ( picked.long ) {
			formatAttributes.title = picked.long;
		}
		if ( picked.lang ) {
			formatAttributes.lang = picked.lang;
		}
		if ( picked.label ) {
			formatAttributes[ 'data-placeholder-title' ] = picked.label;
		}

		const markerLabel = __( 'Placeholder', 'rrze-answers' );
		const markerTitle = picked.label || __( '(no title)', 'rrze-answers' );
		const markerText = `[${ markerLabel }: ${ markerTitle }]`;

		let nextValue = insert( value, markerText );
		nextValue = {
			...nextValue,
			start: nextValue.end - markerText.length,
			end: nextValue.end,
		};
		nextValue = applyFormat(
			nextValue,
			asRichTextFormat( formatAttributes )
		);

		onChange( nextValue );
		setIsOpen( false );
		setSelectedId( '' );
	};

	return (
		<>
			<RichTextShortcut
				type="primaryShift"
				character="S"
				onUse={ () => setIsOpen( true ) }
			/>
			<span ref={ anchorRef }>
				<RichTextToolbarButton
					icon="editor-paste-text"
					title={ __( 'Placeholder', 'rrze-answers' ) }
					onClick={ () => setIsOpen( ( open ) => ! open ) }
					isActive={ isActive }
				/>
			</span>

			{ isOpen && (
				<Popover
					anchorRef={ anchorRef.current ?? undefined }
					variant="toolbar"
					onClose={ () => setIsOpen( false ) }
				>
					<div className="rrze-placeholder-popover">
						{ loading && (
							<Flex align="center" gap={ 8 }>
								<Spinner />
								<span>
									{ __(
										'Loading placeholders…',
										'rrze-answers'
									) }
								</span>
							</Flex>
						) }

						{ ! loading && error && (
							<Notice status="error" isDismissible={ false }>
								{ __(
									'Failed to load placeholders. Check your REST setup.',
									'rrze-answers'
								) }
							</Notice>
						) }

						{ ! loading && ! error && (
							<ComboboxControl
								label={ __(
									'Choose a placeholder',
									'rrze-answers'
								) }
								help={ __(
									'Type to search by title',
									'rrze-answers'
								) }
								value={ selectedId }
								onChange={ ( nextId ) =>
									setSelectedId( nextId ?? '' )
								}
								options={ options }
							/>
						) }

						<Flex
							className="rrze-placeholder-popover-actions"
							justify="flex-end"
							gap={ 8 }
						>
							<FlexItem>
								<Button
									variant="primary"
									onClick={ applyFromSelected }
									disabled={ ! selectedId }
								>
									{ __( 'Insert', 'rrze-answers' ) }
								</Button>
							</FlexItem>
						</Flex>
					</div>
				</Popover>
			) }
		</>
	);
}

const formatRegistration: FormatRegistration = {
	title: __( 'Placeholder', 'rrze-answers' ),
	tagName: 'placeholder',
	className: CLASS_NAME,
	attributes: {
		title: 'title',
		lang: 'lang',
		placeholderId: 'data-placeholder-id',
		placeholderTitle: 'data-placeholder-title',
	},
	edit: PlaceholderUI,
};

registerFormatType(
	FORMAT_NAME,
	formatRegistration as unknown as Parameters<
		typeof registerFormatType
	>[ 1 ]
);
