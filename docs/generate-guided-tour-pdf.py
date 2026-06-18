#!/usr/bin/env python3
"""Generate PDF guide for RRZE Answers admin tours."""

from fpdf import FPDF
from pathlib import Path

OUTPUT = Path(__file__).resolve().parent / "Anleitung-Admin-Tour.pdf"


class GuidePDF(FPDF):
    def header(self):
        self.set_font("DejaVu", "B", 10)
        self.set_text_color(100, 100, 100)
        self.cell(0, 8, "RRZE Answers – Admin-Tour Anleitung", align="R", new_x="LMARGIN", new_y="NEXT")
        self.ln(2)

    def footer(self):
        self.set_y(-15)
        self.set_font("DejaVu", "", 9)
        self.set_text_color(120, 120, 120)
        self.cell(0, 10, f"Seite {self.page_no()}", align="C")

    def chapter_title(self, title: str) -> None:
        self.ln(4)
        self.set_font("DejaVu", "B", 14)
        self.set_text_color(34, 113, 177)
        self.multi_cell(0, 8, title)
        self.ln(2)

    def section_title(self, title: str) -> None:
        self.ln(3)
        self.set_font("DejaVu", "B", 11)
        self.set_text_color(29, 35, 39)
        self.multi_cell(0, 7, title)
        self.ln(1)

    def body_text(self, text: str) -> None:
        self.set_font("DejaVu", "", 10)
        self.set_text_color(29, 35, 39)
        self.multi_cell(0, 5.5, text)
        self.ln(2)

    def bullet(self, text: str) -> None:
        self.set_x(self.l_margin)
        self.set_font("DejaVu", "", 10)
        self.set_text_color(29, 35, 39)
        self.multi_cell(0, 5.5, f"  - {text}")

    def code_block(self, text: str) -> None:
        self.set_x(self.l_margin)
        self.set_fill_color(245, 245, 245)
        self.set_font("DejaVu", "", 8)
        self.set_text_color(30, 30, 30)
        for line in text.split("\n"):
            self.set_x(self.l_margin)
            self.multi_cell(0, 4.5, "  " + line, fill=True)
        self.ln(3)


def build_pdf() -> None:
    pdf = GuidePDF()
    pdf.set_margins(18, 18, 18)
    pdf.set_auto_page_break(auto=True, margin=18)

    font_path = "/System/Library/Fonts/Supplemental/Arial Unicode.ttf"
    pdf.add_font("DejaVu", "", font_path)
    pdf.add_font("DejaVu", "B", font_path)

    pdf.add_page()
    pdf.set_font("DejaVu", "B", 20)
    pdf.set_text_color(34, 113, 177)
    pdf.multi_cell(0, 10, "Anleitung: Admin-Tour in einem\nWordPress-Plugin")
    pdf.ln(2)
    pdf.set_font("DejaVu", "", 11)
    pdf.set_text_color(80, 80, 80)
    pdf.multi_cell(0, 6, "Nach dem Vorbild des Plugins RRZE Answers – für die Umsetzung in anderen Plugins.")
    pdf.ln(4)

    pdf.chapter_title("Überblick")
    pdf.body_text(
        "RRZE Answers bietet zwei Tour-Typen:\n\n"
        "1. Guided Tour – Einführung mit mehreren Info-Seiten (Modal), basierend auf der "
        "WordPress-Komponente Guide aus @wordpress/components.\n\n"
        "2. Setup Tour – Kontextuelle Schritt-für-Schritt-Anleitung auf der Einstellungsseite "
        "mit eigenem React-Overlay und data-*-Ankern im HTML."
    )

    pdf.chapter_title("1. Dateistruktur (rrze-answers)")
    pdf.code_block(
        "src/js/\n"
        "  rrze-answers-guided-tour.js   ← Einstiegspunkt, rendert React-App\n"
        "  setup-tour.js                 ← Kontextuelle Setup-Tour\n"
        "  setup-tour-step.js            ← Wiederverwendbares Schritt-Panel\n\n"
        "includes/Common/Settings/\n"
        "  Settings.php                  ← Script enqueue, AJAX, User-Meta\n"
        "  templates/settings-page.php   ← Buttons + React-Root\n"
        "  templates/tab-menu.php        ← data-rrze-tour auf Tabs\n"
        "  templates/options/*.php       ← data-rrze-tour auf Formularfeldern\n\n"
        "src/sass/rrze-answers-admin.scss\n"
        "build/rrze-answers-guided-tour.js"
    )

    pdf.chapter_title("2. PHP: Script laden und Konfiguration übergeben")
    pdf.body_text("In Settings.php werden Hooks registriert:")
    pdf.bullet("admin_enqueue_scripts – Script nur auf der Plugin-Seite laden")
    pdf.bullet("wp_enqueue_script mit .asset.php aus dem Build")
    pdf.bullet("wp_enqueue_style('wp-components') und dashicons")
    pdf.bullet("wp_set_script_translations() für Übersetzungen")
    pdf.bullet("wp_localize_script() mit autoStart, activeTab, settingsUrl, ajaxUrl, Nonces")
    pdf.bullet("Zwei AJAX-Handler zum Speichern in User Meta (*_dismissed)")
    pdf.ln(2)
    pdf.code_block(
        "add_action('admin_enqueue_scripts', [$this, 'enqueueGuidedTour']);\n"
        "add_action('wp_ajax_rrze_answers_dismiss_guided_tour', ...);\n"
        "add_action('wp_ajax_rrze_answers_dismiss_setup_tour', ...);\n\n"
        "wp_localize_script('rrze-answers-guided-tour', 'rrzeAnswersGuide', [\n"
        "    'autoStart' => !get_user_meta(..., 'rrze_answers_guided_tour_dismissed', true),\n"
        "    'autoStartSetup' => isset($_GET['rrze_setup_tour']),\n"
        "    'setupTourStepId' => $setupTourStepId,\n"
        "    'settingsUrl' => $this->getUrl(),\n"
        "    'activeTab' => $this->getActiveTab()->slug,\n"
        "    'ajaxUrl' => admin_url('admin-ajax.php'),\n"
        "    'nonce' => wp_create_nonce('rrze_answers_guided_tour'),\n"
        "]);"
    )

    pdf.chapter_title("3. HTML: Root-Container und Start-Buttons")
    pdf.code_block(
        '<button type="button" id="rrze-answers-start-guided-tour"\n'
        '        class="page-title-action">Guided tour</button>\n'
        '<button type="button" id="rrze-answers-start-setup-tour"\n'
        '        class="page-title-action">Setup tour</button>\n'
        '<div id="rrze-answers-guided-tour-root"></div>'
    )
    pdf.body_text("React mountet in #rrze-answers-guided-tour-root. Die Button-IDs müssen mit dem JavaScript übereinstimmen.")

    pdf.chapter_title("4. Anker im Markup: data-rrze-tour")
    pdf.body_text("Die Setup-Tour findet UI-Elemente per CSS-Selektor:")
    pdf.bullet('Tabs: data-rrze-tour="tab-domains"')
    pdf.bullet('Felder: data-rrze-tour="new-domain" (bedingt im Template)')
    pdf.bullet('Speichern: data-rrze-tour="save-settings" am submit_button')
    pdf.body_text("Für ein anderes Plugin: Präfix anpassen, z. B. data-meinplugin-tour.")

    pdf.add_page()
    pdf.chapter_title("5. Guided Tour (einfache Einführung)")
    pdf.body_text(
        "Die Guided Tour nutzt die WordPress-Komponente Guide. Jede Seite hat image und content. "
        "Beim Beenden wird per AJAX dismissed in der User Meta gespeichert."
    )
    pdf.code_block(
        "import { Guide } from '@wordpress/components';\n\n"
        "<Guide\n"
        "    className=\"rrze-answers-guided-tour\"\n"
        "    finishButtonText={__('Get started', 'rrze-answers')}\n"
        "    onFinish={finishGuide}\n"
        "    pages={guidePages}\n"
        "/>"
    )

    pdf.chapter_title("6. Setup Tour (kontextuelle Schritt-für-Schritt-Tour)")
    pdf.section_title("6.1 Schritte definieren")
    pdf.code_block(
        "{\n"
        "    id: 'new-domain',\n"
        "    tab: 'domains',\n"
        "    target: '[data-rrze-tour=\"new-domain\"]',\n"
        "    title: __('Add a domain', 'rrze-answers'),\n"
        "    text: __('Enter the URL...', 'rrze-answers'),\n"
        "    optional: true,  // optional: Schritt überspringen wenn target fehlt\n"
        "}"
    )
    pdf.section_title("6.2 Tab-Wechsel über URL")
    pdf.body_text(
        "Wechselt ein Schritt den Tab, lädt die Seite neu mit "
        "?tab=import&rrze_setup_tour=1&rrze_setup_tour_step=import-categories. "
        "PHP liest rrze_setup_tour_step und übergibt setupTourStepId an JavaScript."
    )
    pdf.section_title("6.3 Highlight und Scroll")
    pdf.body_text(
        "Das Ziel-Element erhält die CSS-Klasse rrze-answers-setup-tour__highlight "
        "und wird per scrollIntoView in den sichtbaren Bereich gescrollt."
    )
    pdf.section_title("6.4 Schritt-Panel")
    pdf.body_text(
        "setup-tour-step.js nutzt WordPress-Button mit Zurück, Überspringen und Weiter/Fertig."
    )

    pdf.chapter_title("7. CSS")
    pdf.bullet("__overlay – halbtransparenter Hintergrund (z-index: 99990)")
    pdf.bullet("__highlight – blauer Outline um das Ziel-Element (z-index: 99995)")
    pdf.bullet("__card – fixiertes Panel unten mittig (z-index: 100000)")
    pdf.body_text("Styles in src/sass/rrze-answers-admin.scss, Build mit sass nach build/css/.")

    pdf.chapter_title("8. Build-Pipeline")
    pdf.code_block(
        '"build:js": "wp-scripts build ... src/js/rrze-answers-guided-tour.js"\n'
        "npm run build:js\n"
        "npm run build:css"
    )
    pdf.body_text("Ergebnis: build/rrze-answers-guided-tour.js + .asset.php")

    pdf.add_page()
    pdf.chapter_title("9. Checkliste für ein neues Plugin")
    pdf.section_title("Minimal (nur Guided Tour)")
    for item in [
        "src/js/mein-plugin-guided-tour.js mit Guide-Komponente",
        "React-Root + Start-Button im Admin-Template",
        "enqueueGuidedTour() in PHP",
        "AJAX-Handler dismiss_*_tour",
        "User Meta mein_plugin_guided_tour_dismissed",
        "SCSS + Build",
        "Strings mit __() und Textdomain",
    ]:
        pdf.bullet(item)

    pdf.section_title("Mit Setup Tour zusätzlich")
    for item in [
        "data-meinplugin-tour an Tabs, Feldern, Buttons",
        "setup-tour.js mit Schritt-Array",
        "URL-Parameter für Tab-Wechsel",
        "activeTab per PHP an JS übergeben",
        "buildTourPath() – nur Schritte mit sichtbarem target",
        "Overlay + Highlight-CSS",
    ]:
        pdf.bullet(item)

    pdf.chapter_title("10. Typische Fehler")
    pdf.bullet("Tour startet nicht → Script/Root prüfen, Hook-Bedingung")
    pdf.bullet("Schritt fehlt → data-*-Attribut setzen, optional: true")
    pdf.bullet("Falscher Tab → activeTab und tab in Schritten abgleichen")
    pdf.bullet("Tour bei jedem Besuch → AJAX-Nonce und User Meta prüfen")
    pdf.bullet("Unübersetzte Texte → wp_set_script_translations() + Sprachdateien")

    pdf.chapter_title("11. Zusammenfassung")
    pdf.body_text(
        "RRZE Answers nutzt WordPress-eigene Bausteine (Guide, Button, @wordpress/element) "
        "statt einer externen Tour-Library. Die Guided Tour erklärt das Plugin allgemein; "
        "die Setup Tour verknüpft Schritte mit echten UI-Elementen über data-rrze-tour-Anker "
        "und Tab-Navigation per URL.\n\n"
        "Für ein anderes Plugin: Präfixe, Schritte und Anker anpassen, Build-Pipeline "
        "einrichten – die Architektur bleibt gleich."
    )

    pdf.set_font("DejaVu", "", 9)
    pdf.set_text_color(120, 120, 120)
    pdf.ln(6)
    pdf.multi_cell(0, 5, "Erstellt aus der RRZE Answers Codebasis – github.com/RRZE-Webteam/rrze-answers")

    pdf.output(OUTPUT)
    print(f"PDF erstellt: {OUTPUT}")


if __name__ == "__main__":
    build_pdf()
