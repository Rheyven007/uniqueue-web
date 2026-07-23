(function () {

    const form = document.getElementById("qtFilterForm");

    if (!form) return;


    const tableWrapper = document.querySelector(".qt-table-wrap");


    let timer = null;


    function loadTransactions(page = 1) {


        const formData = new FormData(form);

        const params = new URLSearchParams();


        formData.forEach((value, key) => {

            if (value !== "") {
                params.append(key, value);
            }

        });


        params.set("page", page);



        fetch(
            "queue-transaction-data.php?" + params.toString(),
            {
                method: "GET",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            }
        )

        .then(response => response.text())

        .then(html => {


            if (tableWrapper) {

                tableWrapper.innerHTML = html;

            }


            bindPagination();


        })

        .catch(error => {

            console.error(
                "Queue transaction error:",
                error
            );

        });


    }



    /*
    |--------------------------------------------------------------------------
    | Search typing
    |--------------------------------------------------------------------------
    */

    const search = document.getElementById("qtSearch");


    if(search){

        search.addEventListener(
            "input",
            function(){

                clearTimeout(timer);


                timer = setTimeout(
                    function(){

                        loadTransactions();

                    },
                    300
                );


            }
        );

    }



    /*
    |--------------------------------------------------------------------------
    | Dropdown / Date filters
    |--------------------------------------------------------------------------
    */

    [
        "qtStatus",
        "qtType",
        "qtFrom",
        "qtTo"

    ].forEach(function(id){


        const element =
            document.getElementById(id);


        if(element){


            element.addEventListener(
                "change",
                function(){

                    loadTransactions();

                }
            );


        }


    });





    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    function bindPagination(){


        document
        .querySelectorAll(".qt-pagination a")
        .forEach(function(link){


            link.addEventListener(
                "click",
                function(e){


                    e.preventDefault();


                    const url =
                        new URL(
                            this.href
                        );


                    loadTransactions(
                        url.searchParams.get("page")
                    );


                }
            );


        });


    }



    bindPagination();



})();