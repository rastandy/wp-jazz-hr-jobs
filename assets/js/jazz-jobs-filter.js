class FilterDropdowns {
    constructor() {
        this.jobs = document.querySelectorAll(".job-listing");

        this.filters = {
            location: "",
            department: "",
            commitment: ""
        };
        this.bindFilterChangeHandler();
    }

    bindFilterChangeHandler() {
        document.querySelectorAll("select.filter").forEach((filter) => {
            filter.addEventListener(
                "change",
                (e) => {
                    var offsetY = window.pageYOffset;

                    this.filters[e.currentTarget.dataset.filter] = e.currentTarget.value;
                    this.filterJobs();

                    window.scrollTo({
                        top: offsetY,
                        behavior: "smooth"
                    });
                },
                false
            );
        });
    }

    filterJobs() {
        // Concatenate the featured and normal jobs arrays
        let tmpJobs = this.jobs;

        // set show data value on all jobs to visible
        tmpJobs.forEach((job) => {
            job.dataset.show = "true";
        });

        // Now start filtering and setting the show data value
        Object.keys(this.filters).forEach((filterTxt) => {
            tmpJobs.forEach((jobEl) => {
                let jobFilterTxt =
                    filterTxt === "location"
                        ? "filterLocation"
                        : filterTxt === "department"
                        ? "filterDepartment"
                        : "filterCommitment";
                if (jobEl.dataset[jobFilterTxt] !== this.filters[filterTxt] && this.filters[filterTxt] !== "") {
                    jobEl.dataset.show = "false";
                }
            });
        });

        // Change display value
        var visibleJobs = this.jobs.length;

        tmpJobs.forEach((job) => {
            if (job.dataset.show == "false") {
                job.style.display = "none";
                visibleJobs -= 1;
            } else if (job.dataset.show == "true") {
                job.style.display = "flex";
            }
        });

        if (visibleJobs === 0) {
            document.querySelector(".filter-results .no-results-message").classList.contains("hidden") &&
                document.querySelector(".filter-results .no-results-message").classList.remove("hidden");
            // document.querySelector('.job-section_featured').style.display = "none";
        } else {
            !document.querySelector(".filter-results .no-results-message").classList.contains("hidden") &&
                document.querySelector(".filter-results .no-results-message").classList.add("hidden");
            // document.querySelector('.job-section_featured').style.display = "block";
        }

        // Check if headings are empty
        document.querySelectorAll(".job-section, .job-section_featured").forEach((section) => {
            let showHeading = false;
            Object.entries(section.querySelectorAll("ul.job-listings li.job-listing")).forEach((job) => {
                if (job[1].dataset.show == "true") {
                    showHeading = true;
                }
            });
            if (showHeading) {
                section.style.display = "flex";
            } else {
                section.style.display = "none";
            }
        });
    }
}

window.onload = function () {
    new FilterDropdowns();
};

class GridManager {
    constructor(gridSelector, itemSelector, itemsPerPage) {
        this.grid = document.querySelector(gridSelector);
        this.items = this.grid.querySelectorAll(itemSelector);
        this.display = this.items[0].style.display
        this.filteredItems = Array.from(this.items); // Copia degli item per il filtering
        this.itemsPerPage = itemsPerPage || 10; // Imposta il valore predefinito a 10 se non specificato
        this.currentPage = 0;
    }

    filterItems(filterSelector) {
        this.filteredItems = Array.from(this.items).filter(item => item.matches(filterSelector));
        this.currentPage = 0; // Resetta alla prima pagina quando si applica un filtro
        this.showPage();
    }

    showPage() {
        const startIndex = this.currentPage * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;

        this.items.forEach((item, index) => {
            if (index >= startIndex && index < endIndex) {
                item.style.display = this.display;
            } else {
                item.style.display = 'none';
            }
        });
    }

    nextPage() {
        const totalPages = Math.ceil(this.filteredItems.length / this.itemsPerPage);
        if (this.currentPage < totalPages - 1) {
            this.currentPage++;
            this.showPage();
        }
    }

    previousPage() {
        if (this.currentPage > 0) {
            this.currentPage--;
            this.showPage();
        }
    }
}
