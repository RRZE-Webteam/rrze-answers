declare module '@wordpress/block-editor' {
	import type {
		ComponentType,
		HTMLAttributes,
		PropsWithChildren,
		RefObject,
	} from 'react';
	import type { RichTextValue } from '@wordpress/rich-text';

	export const BlockControls: ComponentType<PropsWithChildren>;
	export const InspectorControls: ComponentType<PropsWithChildren>;

	export interface HeadingLevelDropdownProps {
		options: number[];
		value?: number;
		onChange: ( value: number ) => void;
	}

	export const HeadingLevelDropdown: ComponentType<HeadingLevelDropdownProps>;

	export function useBlockProps(
		props?: HTMLAttributes<HTMLDivElement>
	): HTMLAttributes<HTMLDivElement>;

	export interface RichTextShortcutProps {
		type: 'primary' | 'primaryShift' | 'access';
		character: string;
		onUse: () => void;
	}

	export const RichTextShortcut: ComponentType<RichTextShortcutProps>;

	export interface RichTextToolbarButtonProps {
		icon: string;
		title: string;
		onClick: () => void;
		isActive?: boolean;
	}

	export const RichTextToolbarButton: ComponentType<RichTextToolbarButtonProps>;

	export interface RichTextFormatEditProps {
		value: RichTextValue;
		onChange: ( value: RichTextValue ) => void;
		isActive: boolean;
		activeAttributes?: Record<string, string>;
		contentRef?: RefObject<HTMLElement>;
	}
}
