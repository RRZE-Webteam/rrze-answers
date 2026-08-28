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
import {
	applyFormat,
	getActiveFormat,
	insert,
	registerFormatType,
	removeFormat,
} from '@wordpress/rich-text';

import type { RichTextFormatEditProps } from '@wordpress/block-editor';

const FORMAT_NAME = 'rrze/synonym';
const TAG_NAME = 'abbr';
const CLASS_NAME = 'rrze-syn';

interface SynonymRecord {
	id: number;
	title?: {
		rendered?: string;
	};
	synonym?: string;
	titleLang?: string;
	meta?: {
		synonym?: string;
		titleLang?: string;
	};
}

interface SynonymOption {
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

function SynonymUI( { value, onChange, isActive }: RichTextFormatEditProps ) {
	const [ items, setItems ] = useState< SynonymRecord[] >( [] );
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
			const records: SynonymRecord[] = [];

			try {
				while ( true ) {
					const batch = await apiFetch< SynonymRecord[] >( {
						path: `/wp/v2/synonym?status=publish&per_page=${ perPage }&page=${ page }&orderby=title&order=asc&_fields=id,title,synonym,titleLang,meta`,
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

	const options = useMemo< SynonymOption[] >(
		() =>
			items.map( ( post ) => ( {
				value: String( post.id ),
				label:
					post.title?.rendered || __( '(no title)', 'rrze-answers' ),
				long: post.synonym ?? post.meta?.synonym ?? '',
				lang: post.titleLang ?? post.meta?.titleLang ?? '',
			} ) ),
		[ items ]
	);

	const current = getActiveFormat( value, FORMAT_NAME );

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

		const formatAttributes: Record< string, string > = {};
		if ( picked.long ) {
			formatAttributes.title = picked.long;
		}
		if ( picked.lang ) {
			formatAttributes.lang = picked.lang;
		}

		let nextValue = value;
		if ( nextValue.start === nextValue.end && picked.label ) {
			const previousLength = nextValue.text.length;
			nextValue = insert( nextValue, picked.label );
			const insertedLength = nextValue.text.length - previousLength;
			nextValue = {
				...nextValue,
				start: nextValue.end - insertedLength,
				end: nextValue.end,
			};
		}

		nextValue = applyFormat(
			nextValue,
			asRichTextFormat( formatAttributes )
		);
		onChange( nextValue );
		setIsOpen( false );
		setSelectedId( '' );
	};

	const removeFormatHere = () => {
		onChange( removeFormat( value, FORMAT_NAME ) );
		setIsOpen( false );
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
					icon="translation"
					title={ __( 'Synonym', 'rrze-answers' ) }
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
					<div className="rrze-synonym-popover">
						{ loading && (
							<Flex align="center" gap={ 8 }>
								<Spinner />
								<span>
									{ __(
										'Loading synonyms…',
										'rrze-answers'
									) }
								</span>
							</Flex>
						) }

						{ ! loading && error && (
							<Notice status="error" isDismissible={ false }>
								{ __(
									'Failed to load synonyms. Check your REST setup.',
									'rrze-answers'
								) }
							</Notice>
						) }

						{ ! loading && ! error && (
							<ComboboxControl
								label={ __(
									'Choose a synonym',
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
							className="rrze-synonym-popover-actions"
							justify="flex-end"
							gap={ 8 }
						>
							{ current && (
								<FlexItem>
									<Button
										variant="secondary"
										onClick={ removeFormatHere }
									>
										{ __( 'Remove', 'rrze-answers' ) }
									</Button>
								</FlexItem>
							) }
							<FlexItem>
								<Button
									variant="primary"
									onClick={ applyFromSelected }
									disabled={ ! selectedId }
								>
									{ current
										? __( 'Update', 'rrze-answers' )
										: __( 'Apply', 'rrze-answers' ) }
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
	title: __( 'Synonym', 'rrze-answers' ),
	tagName: TAG_NAME,
	className: CLASS_NAME,
	attributes: {
		title: 'title',
		lang: 'lang',
		'data-pron': 'data-pron',
	},
	edit: SynonymUI,
};

registerFormatType(
	FORMAT_NAME,
	formatRegistration as unknown as Parameters<
		typeof registerFormatType
	>[ 1 ]
);
