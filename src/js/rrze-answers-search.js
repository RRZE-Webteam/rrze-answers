(function () {
  "use strict";

  /**
   * Normalize a string for comparison
   */
  function normalize(str) {
    return (str || "").toString().trim().toLowerCase();
  }

  /**
   * When Schema.org markup is active, <details> is wrapped
   * in a Question <div>. We must hide/show that wrapper instead.
   */
  function getToggleElement(detailsEl) {
    const schemaWrapper = detailsEl.closest(
      '[itemscope][itemtype="https://schema.org/Question"]'
    );
    return schemaWrapper || detailsEl;
  }

  /**
   * Initialize search for a single FAQ wrapper
   */
  function initFAQSearch(wrapper) {
    if (!wrapper || wrapper.dataset.rrzeFaqSearchInit === "1") return;

    const input = wrapper.querySelector(".rrze-answers-search__input");
    if (!input) return;

    const detailsItems = Array.from(
      wrapper.querySelectorAll("details.rrze-answers-item")
    );
    if (!detailsItems.length) return;

    const minLen = parseInt(input.getAttribute("data-minlen") || "3", 10);

    // Cache FAQ questions (summary text only)
    const items = detailsItems.map((details) => {
      const summary = details.querySelector("summary");
      return {
        details,
        question: normalize(summary ? summary.textContent : ""),
      };
    });

    /**
     * Apply filtering based on input value
     */
    function applyFilter(value) {
      const query = normalize(value);

      // Reset if query is too short
      if (query.length < minLen) {
        items.forEach(({ details }) => {
          getToggleElement(details).style.display = "";
        });
        return;
      }

      items.forEach(({ details, question }) => {
        const match = question.includes(query);
        getToggleElement(details).style.display = match ? "" : "none";
      });
    }

    input.addEventListener("input", () => applyFilter(input.value));

    // Optional UX improvement: ESC clears the search
    input.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        input.value = "";
        applyFilter("");
      }
    });

    // Mark wrapper as initialized to avoid duplicate listeners
    wrapper.dataset.rrzeFaqSearchInit = "1";
  }

  /**
   * Initialize all FAQ instances in a given DOM scope
   */
  function initAll(root = document) {
    root.querySelectorAll(".rrze-answers").forEach(initFAQSearch);
  }

  // Initial run (frontend + initial editor render)
  initAll();

  /**
   * Block editor support:
   * ServerSideRender replaces markup dynamically,
   * so we observe DOM mutations and re-initialize as needed.
   */
  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      for (const node of mutation.addedNodes) {
        if (!(node instanceof HTMLElement)) continue;

        // Node itself may be an FAQ wrapper
        if (node.matches && node.matches(".rrze-answers")) {
          initFAQSearch(node);
        }

        // Or may contain FAQ wrappers
        if (node.querySelectorAll) {
          initAll(node);
        }
      }
    }
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });
})();
