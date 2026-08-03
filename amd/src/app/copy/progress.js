import BaseFactory from '../factory';
import SELECTORS from './selectors';

const POLL_INTERVAL_MS = 3000;
// A transient network hiccup shouldn't permanently freeze the progress page while the job is
// still genuinely running server-side - retry a bounded number of times before giving up.
const MAX_POLL_RETRIES = 5;

export default class Progress {
    /**
     * @type {BaseFactory}
     */
    #baseFactory;

    /**
     * @param {BaseFactory} baseFactory
     */
    constructor(baseFactory) {
        this.#baseFactory = baseFactory;
    }

    /**
     * Renders one poll response into the page.
     * @param {Element} root
     * @param {Object} response
     * @return {Promise}
     */
    async #render(root, response) {
        root.dataset.status = response.status;
        root.querySelector(SELECTORS.COPY_PROGRESS_BAR).style.width = `${response.percent}%`;
        root.querySelector(SELECTORS.COPY_PROGRESS_PERCENT).textContent = `${response.percent}%`;
        root.querySelector(SELECTORS.COPY_PROGRESS_STATUS_LABEL).textContent = response.statuslabel;

        const ajax = this.#baseFactory.moodle().ajax();
        const template = this.#baseFactory.moodle().template();

        const [countText, {html, js}] = await Promise.all([
            ajax.getString('copyprogressheading', 'block_activity_copy_cart', {
                completedunits: response.completedunits,
                totalunits: response.totalunits,
            }),
            template.renderTemplate('block_activity_copy_cart/copy/results', {results: response.results}),
        ]);

        root.querySelector(SELECTORS.COPY_PROGRESS_COUNT).textContent = countText;
        template.replaceNodeContents(root.querySelector(SELECTORS.COPY_RESULTS_BODY), html, js);
        return null;
    }

    /**
     * Fetches one poll response and schedules the next one, unless the job has
     * reached a terminal state - stopping on every terminal status (not just
     * 'completed'), which is the one thing worth calling out: a sibling plugin's
     * own equivalent poller only stops on the literal string 'completed' and
     * polls forever after a failure.
     * @param {number} jobid
     * @param {number} [retriesLeft] - Remaining retry budget for a transient (network/server) error
     */
    async #poll(jobid, retriesLeft = MAX_POLL_RETRIES) {
        const root = document.querySelector(SELECTORS.COPY_PROGRESS_ROOT);
        if (!root) {
            return;
        }

        const ajax = this.#baseFactory.moodle().ajax();
        try {
            const response = await ajax.call('block_activity_copy_cart_get_job_progress', {jobid});
            await this.#render(root, response);
            if (!response.isterminal) {
                // Reset the retry budget on every success, so an earlier blip doesn't eat into
                // a later, unrelated one.
                window.setTimeout(() => this.#poll(jobid, MAX_POLL_RETRIES), POLL_INTERVAL_MS);
            }
        } catch (error) {
            if (retriesLeft > 0) {
                window.setTimeout(() => this.#poll(jobid, retriesLeft - 1), POLL_INTERVAL_MS);
                return;
            }
            ajax.notifyException(error);
        }
    }

    /**
     * @param {number} jobid
     */
    start(jobid) {
        this.#poll(jobid);
    }

    /**
     * Static initialization called by Moodle PHP (copy_progress.php).
     * @param {number} jobid
     */
    static init(jobid) {
        const baseFactory = BaseFactory.make(); // No reactive component on this page.
        new this(baseFactory).start(jobid);
    }
}
