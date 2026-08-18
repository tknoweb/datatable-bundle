import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        scrollOnChange: Boolean,
    }

    connect() {
    }

    /**
     * Changing the datatable max limit parameter
     */
    changeLimitMax(event) {
        let element = event.target;
        let location = new URL(element.options[element.selectedIndex].dataset.url, document.baseURI);
        location.searchParams.append('resetPagination', '1');

        let frame = element.closest(".datatable-turbo-frame");
        if (frame && typeof Turbo !== 'undefined') {
            Turbo.visit(location, {frame: frame.id});
        } else {
            window.location.assign(location);
        }

        this.scrollToTop();
    }

    /**
     * Changing the datatable current page (offset).
     * The link href handles the navigation, whether inside a turbo frame or not.
     */
    changePage(event) {
        if (event.target.closest(".datatable-turbo-frame")) {
            this.scrollToTop();
        }
    }

    scrollToTop() {
        if (this.scrollOnChangeValue) {
            window.scroll({
                top: 0,
                left: 0,
                behavior: 'smooth'
            });
        }
    }
}
