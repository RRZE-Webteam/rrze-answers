/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./blocks/synonym/edit.js":
/*!********************************!*\
  !*** ./blocks/synonym/edit.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/server-side-render */ "@wordpress/server-side-render");
/* harmony import */ var _wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */







function Edit({
  attributes,
  setAttributes
}) {
  const {
    register,
    tag,
    id,
    hstart,
    order,
    sort,
    lang,
    additional_class,
    color,
    load_open,
    expand_all_link,
    hide_title,
    hide_accordion,
    synonymstyle,
    synonym
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__.useBlockProps)();
  const [registerstate, setSelectedCategories] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(['']);
  const [tagstate, setSelectedTags] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(['']);
  const [idstate, setSelectedIDs] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(['']);
  const categories = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.useSelect)(select => {
    return select('core').getEntityRecords('taxonomy', 'synonym_category');
  }, []);
  const registeroptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('all', 'rrze-answers'),
    value: ''
  }];
  if (!!categories) {
    Object.values(categories).forEach(register => {
      registeroptions.push({
        label: register.name,
        value: register.slug
      });
    });
  }
  const tags = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.useSelect)(select => {
    return select('core').getEntityRecords('taxonomy', 'synonym_tag');
  }, []);
  const tagoptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('all', 'rrze-answers'),
    value: ''
  }];
  if (!!tags) {
    Object.values(tags).forEach(tag => {
      tagoptions.push({
        label: tag.name,
        value: tag.slug
      });
    });
  }
  const synonyms = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.useSelect)(select => {
    return select('core').getEntityRecords('postType', 'synonym', {
      per_page: -1,
      orderby: 'title',
      order: "asc"
    });
  }, []);
  const synonymoptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('all', 'rrze-answers'),
    value: 0
  }];
  if (!!synonyms) {
    Object.values(synonyms).forEach(synonym => {
      synonymoptions.push({
        label: synonym.title.rendered ? synonym.title.rendered : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No title', 'rrze-answers'),
        value: synonym.id
      });
    });
  }
  const langoptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('all', 'rrze-faq'),
    value: ''
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('German', 'rrze-faq'),
    value: 'de'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('English', 'rrze-faq'),
    value: 'en'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('French', 'rrze-faq'),
    value: 'fr'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Spanish', 'rrze-faq'),
    value: 'es'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Russian', 'rrze-faq'),
    value: 'ru'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Chinese', 'rrze-faq'),
    value: 'zh'
  }];
  const synonymstyleoptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('-- hidden --', 'rrze-answers'),
    value: ''
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('A - Z', 'rrze-answers'),
    value: 'a-z'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Tagcloud', 'rrze-answers'),
    value: 'tagcloud'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Tabs', 'rrze-answers'),
    value: 'tabs'
  }];
  const coloroptions = [{
    label: 'fau',
    value: 'fau'
  }, {
    label: 'med',
    value: 'med'
  }, {
    label: 'nat',
    value: 'nat'
  }, {
    label: 'phil',
    value: 'phil'
  }, {
    label: 'rw',
    value: 'rw'
  }, {
    label: 'tf',
    value: 'tf'
  }];
  const sortoptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Title', 'rrze-answers'),
    value: 'title'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('ID', 'rrze-answers'),
    value: 'id'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Sort field', 'rrze-answers'),
    value: 'sortfield'
  }];
  const orderoptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('ASC', 'rrze-answers'),
    value: 'ASC'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('DESC', 'rrze-answers'),
    value: 'DESC'
  }];

  // console.log('edit.js attributes: ' + JSON.stringify(attributes));

  const onChangeID = newValues => {
    setSelectedIDs(newValues);
    setAttributes({
      id: String(newValues)
    });
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.PanelBody, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("synonym", 'rrze-answers'),
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show a selection of individual Synonyms', 'rrze-answers'),
          value: idstate,
          options: synonymoptions,
          onChange: onChangeID,
          multiple: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("Language", 'rrze-answers'),
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Show only Synonyms matching the selected language.', 'rrze-answers'),
          value: lang,
          options: langoptions,
          onChange: value => setAttributes({
            lang: value
          })
        })]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)((_wordpress_server_side_render__WEBPACK_IMPORTED_MODULE_5___default()), {
        block: "rrze-answers/synonym",
        attributes: attributes
      })
    })]
  });
}

/***/ }),

/***/ "./blocks/synonym/save.js":
/*!********************************!*\
  !*** ./blocks/synonym/save.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ save)
/* harmony export */ });
//It's a dynamic block
function save() {
  return null;
}

/***/ }),

/***/ "./blocks/synonym/synonym-format.js":
/*!******************************************!*\
  !*** ./blocks/synonym/synonym-format.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_rich_text__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/rich-text */ "@wordpress/rich-text");
/* harmony import */ var _wordpress_rich_text__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_rich_text__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
// synonym-format.js — pick from CPT "synonym" and apply <abbr>







const FORMAT_NAME = 'rrze/synonym';
const TAG_NAME = 'abbr';
const CLASS_NAME = 'rrze-syn';
const SynonymUI = props => {
  const {
    value,
    onChange,
    isActive
  } = props;
  const [items, setItems] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)([]);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)(true);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)(null);

  // Fetch all synonyms via REST (uses pagination).
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useEffect)(() => {
    let cancelled = false;
    async function loadAll() {
      setLoading(true);
      setError(null);
      const perPage = 100; // WP REST default max
      let page = 1;
      const out = [];
      try {
        while (true) {
          const batch = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5___default()({
            path: `/wp/v2/synonym?status=publish&per_page=${perPage}&page=${page}&orderby=title&order=asc&_fields=id,title,synonym,titleLang,meta`
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
    return () => {
      cancelled = true;
    };
  }, []);

  // Build options: short = post title, long/lang from top-level REST fields (fallback: meta).
  const options = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useMemo)(() => {
    return (items || []).map(post => {
      var _ref, _post$synonym, _ref2, _post$titleLang;
      return {
        value: String(post.id),
        label: post?.title?.rendered || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('(no title)', 'rrze-synonym'),
        long: (_ref = (_post$synonym = post?.synonym) !== null && _post$synonym !== void 0 ? _post$synonym : post?.meta?.synonym) !== null && _ref !== void 0 ? _ref : '',
        lang: (_ref2 = (_post$titleLang = post?.titleLang) !== null && _post$titleLang !== void 0 ? _post$titleLang : post?.meta?.titleLang) !== null && _ref2 !== void 0 ? _ref2 : ''
      };
    });
  }, [items]);
  const [isOpen, setIsOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)(false);
  const [selectedId, setSelectedId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)('');
  const anchorRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useRef)();

  // If the cursor sits in an existing <abbr>, we allow "Remove" / "Update"
  const current = (0,_wordpress_rich_text__WEBPACK_IMPORTED_MODULE_1__.getActiveFormat)(value, FORMAT_NAME);
  const applyFromSelected = () => {
    if (!selectedId) return;
    const picked = options.find(o => o.value === selectedId);
    if (!picked) return;
    const attrs = {};
    if (picked.long) attrs.title = picked.long;
    if (picked.lang) attrs.lang = picked.lang;
    let v = value;
    const hasSelection = v.start !== v.end;

    // If there is no selection, insert the short label and then wrap it
    if (!hasSelection) {
      const shortText = picked.label || '';
      if (shortText) {
        const beforeLen = v.text.length;
        v = (0,_wordpress_rich_text__WEBPACK_IMPORTED_MODULE_1__.insert)(v, shortText);
        const afterLen = v.text.length;
        const insertedLen = afterLen - beforeLen;
        // select the inserted text
        v = {
          ...v,
          start: v.end - insertedLen,
          end: v.end
        };
      }
    }
    v = (0,_wordpress_rich_text__WEBPACK_IMPORTED_MODULE_1__.applyFormat)(v, {
      type: FORMAT_NAME,
      attributes: attrs
    });
    onChange(v);
    setIsOpen(false);
    setSelectedId('');
  };
  const removeFormatHere = () => {
    onChange((0,_wordpress_rich_text__WEBPACK_IMPORTED_MODULE_1__.removeFormat)(value, FORMAT_NAME));
    setIsOpen(false);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.RichTextShortcut, {
      type: "primaryShift",
      character: "S",
      onUse: () => setIsOpen(true)
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
      ref: anchorRef,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.RichTextToolbarButton, {
        icon: "translation",
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Synonym/Acronym', 'rrze-synonym'),
        onClick: () => setIsOpen(o => !o),
        isActive: isActive
      })
    }), isOpen && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Popover, {
      anchorRef: anchorRef.current,
      variant: "toolbar",
      onClose: () => setIsOpen(false),
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          padding: 12,
          width: 360,
          maxWidth: '90vw'
        },
        children: [loading && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Flex, {
          align: "center",
          gap: 8,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Spinner, {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Loading synonyms…', 'rrze-synonym')
          })]
        }), !loading && error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
          status: "error",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Failed to load synonyms. Check your REST setup.', 'rrze-synonym')
        }), !loading && !error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ComboboxControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Choose a synonym', 'rrze-synonym'),
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Type to search by title', 'rrze-synonym'),
          value: selectedId,
          onChange: setSelectedId,
          options: options
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Flex, {
          style: {
            marginTop: 10
          },
          justify: "flex-end",
          gap: 8,
          children: [!!current && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FlexItem, {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
              variant: "secondary",
              onClick: removeFormatHere,
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Remove', 'rrze-synonym')
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FlexItem, {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
              variant: "primary",
              onClick: applyFromSelected,
              disabled: !selectedId,
              children: !!current ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Update', 'rrze-synonym') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Apply', 'rrze-synonym')
            })
          })]
        })]
      })
    })]
  });
};

// Register the format: renders <abbr class="rrze-syn" ...>…</abbr>
(0,_wordpress_rich_text__WEBPACK_IMPORTED_MODULE_1__.registerFormatType)(FORMAT_NAME, {
  title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Synonym/Acronym', 'rrze-synonym'),
  tagName: TAG_NAME,
  className: CLASS_NAME,
  attributes: {
    title: 'title',
    lang: 'lang',
    'data-pron': 'data-pron' // reserved for future use: pronounciation 
  },
  edit: SynonymUI
});

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/rich-text":
/*!**********************************!*\
  !*** external ["wp","richText"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["richText"];

/***/ }),

/***/ "@wordpress/server-side-render":
/*!******************************************!*\
  !*** external ["wp","serverSideRender"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wp"]["serverSideRender"];

/***/ }),

/***/ "./blocks/synonym/block.json":
/*!***********************************!*\
  !*** ./blocks/synonym/block.json ***!
  \***********************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"rrze-answers/synonym","version":"3.0.4","title":"Synonym","category":"rrze","description":"Display synonyms","icon":"translation","example":{},"attributes":{"register":{"type":"string"},"registerstyle":{"type":"string"},"category":{"type":"string"},"tag":{"type":"string"},"id":{"type":"string"},"hide_accordion":{"type":"boolean"},"hide_title":{"type":"boolean"},"expand_all_link":{"type":"boolean"},"load_open":{"type":"boolean"},"color":{"type":"string"},"additional_class":{"type":"string"},"lang":{"type":"string"},"sort":{"type":"string"},"order":{"type":"string"},"hstart":{"type":"number"}},"supports":{"html":false},"textdomain":"rrze-answers","editorScript":"file:../../build/synonym.js","render":"file:./render.php"}');

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!*********************************!*\
  !*** ./blocks/synonym/index.js ***!
  \*********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _synonym_format_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./synonym-format.js */ "./blocks/synonym/synonym-format.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./block.json */ "./blocks/synonym/block.json");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./edit */ "./blocks/synonym/edit.js");
/* harmony import */ var _save__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./save */ "./blocks/synonym/save.js");
/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */

 // <-- registers the toolbar-button to use it inline

/**
 * Internal dependencies
 */

 // <-- we need the block to output the list of synonyms

(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_3__["default"],
  save: _save__WEBPACK_IMPORTED_MODULE_4__["default"]
});
})();

/******/ })()
;
//# sourceMappingURL=synonym.js.map