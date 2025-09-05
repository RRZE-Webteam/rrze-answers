// synonym-format.js — pick from CPT "synonym" and apply <abbr>
import { __ } from '@wordpress/i18n';
import {
	registerFormatType,
	applyFormat,
	removeFormat,
	getActiveFormat,
	insert,
} from '@wordpress/rich-text';
import {
	RichTextToolbarButton,
	RichTextShortcut,
} from '@wordpress/block-editor';
import {
	Popover,
	ComboboxControl,
	Button,
	Flex,
	FlexItem,
	Spinner,
	Notice,
} from '@wordpress/components';
import { useState, useRef, useEffect, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const FORMAT_NAME = 'rrze/synonym';
const TAG_NAME = 'abbr';
const CLASS_NAME = 'rrze-syn';

const SynonymUI = ( props ) => {
	const { value, onChange, isActive } = props;

	const [items, setItems] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	// Fetch all synonyms via REST (uses pagination).
	useEffect(() => {
		let cancelled = false;

		async function loadAll() {
			setLoading(true);
			setError(null);
			const perPage = 100; // WP REST default max
			let page = 1;
			const out = [];

			try {
				while (true) {
					const batch = await apiFetch({
						path: `/wp/v2/synonym?status=publish&per_page=${perPage}&page=${page}&orderby=title&order=asc&_fields=id,title,synonym,titleLang,meta`,
					});
					if (cancelled) return;

					out.push(...batch);
					if (batch.length < perPage) break;
					page++;
				}
				setItems(out);
			} catch (e) {
				if (!cancelled) setError(e);
			} finally {
				if (!cancelled) setLoading(false);
			}
		}

		loadAll();
		return () => { cancelled = true; };
	}, []);

	// Build options: short = post title, long/lang from top-level REST fields (fallback: meta).
	const options = useMemo(() => {
		return (items || []).map(post => ({
			value: String(post.id),
			label: post?.title?.rendered || __('(no title)','rrze-anwers'),
			long:  post?.synonym ?? post?.meta?.synonym ?? '',
			lang:  post?.titleLang ?? post?.meta?.titleLang ?? '',
		}));
	}, [items]);

	const [ isOpen, setIsOpen ] = useState(false);
	const [ selectedId, setSelectedId ] = useState('');
	const anchorRef = useRef();

	// If the cursor sits in an existing <abbr>, we allow "Remove" / "Update"
	const current = getActiveFormat( value, FORMAT_NAME );

	const applyFromSelected = () => {
		if ( !selectedId ) return;

		const picked = options.find( o => o.value === selectedId );
		if ( !picked ) return;

		const attrs = {};
		if ( picked.long ) attrs.title = picked.long;
		if ( picked.lang ) attrs.lang = picked.lang;

		let v = value;
		const hasSelection = v.start !== v.end;

		// If there is no selection, insert the short label and then wrap it
		if ( !hasSelection ) {
			const shortText = picked.label || '';
			if ( shortText ) {
				const beforeLen = v.text.length;
				v = insert( v, shortText );
				const afterLen = v.text.length;
				const insertedLen = afterLen - beforeLen;
				// select the inserted text
				v = { ...v, start: v.end - insertedLen, end: v.end };
			}
		}

		v = applyFormat( v, { type: FORMAT_NAME, attributes: attrs } );
		onChange( v );
		setIsOpen(false);
		setSelectedId('');
	};

	const removeFormatHere = () => {
		onChange( removeFormat( value, FORMAT_NAME ) );
		setIsOpen(false);
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
					title={ __('Synonym/Acronym','rrze-anwers') }
					onClick={ () => setIsOpen( (o) => !o ) }
					isActive={ isActive }
				/>
			</span>

			{ isOpen && (
				<Popover
					anchorRef={ anchorRef.current }
					variant="toolbar"
					onClose={ () => setIsOpen( false ) }
				>
					<div style={{ padding: 12, width: 360, maxWidth: '90vw' }}>
						{ loading && (
							<Flex align="center" gap={8}>
								<Spinner />
								<span>{ __('Loading synonyms…','rrze-anwers') }</span>
							</Flex>
						) }

						{ (!loading && error) && (
							<Notice status="error" isDismissible={ false }>
								{ __('Failed to load synonyms. Check your REST setup.','rrze-anwers') }
							</Notice>
						) }

						{ (!loading && !error) && (
							<ComboboxControl
								label={ __('Choose a synonym','rrze-anwers') }
								help={ __('Type to search by title','rrze-anwers') }
								value={ selectedId }
								onChange={ setSelectedId }
								options={ options }
							/>
						) }

						<Flex style={{ marginTop: 10 }} justify="flex-end" gap={ 8 }>
							{ !!current && (
								<FlexItem>
									<Button variant="secondary" onClick={ removeFormatHere }>
										{ __('Remove','rrze-anwers') }
									</Button>
								</FlexItem>
							) }
							<FlexItem>
								<Button
									variant="primary"
									onClick={ applyFromSelected }
									disabled={ !selectedId }
								>
									{ !!current ? __('Update','rrze-anwers') : __('Apply','rrze-anwers') }
								</Button>
							</FlexItem>
						</Flex>
					</div>
				</Popover>
			) }
		</>
	);
};

// Register the format: renders <abbr class="rrze-syn" ...>…</abbr>
registerFormatType( FORMAT_NAME, {
	title: __('Synonym/Acronym','rrze-anwers'),
	tagName: TAG_NAME,
	className: CLASS_NAME,
	attributes: {
		title: 'title',
		lang: 'lang',
		'data-pron': 'data-pron', // reserved for future use: pronounciation 
	},
	edit: SynonymUI,
} );
