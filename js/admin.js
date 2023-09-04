

const checkStatuses = ['wc-cancelled', 'wc-refunded'];

jQuery(document).ready(function($) {
    $('#financeStatusModal').dialog({
        autoOpen:false, 
        draggable:false,
        dialogClass: 'wp-dialog',
        create: function () {
            // style fix for WordPress admin
            $('.ui-dialog-titlebar-close').addClass('ui-button');
        }
    });
    const saveBtn = document.getElementsByClassName('save_order')[0];

    const currentStatus = document.getElementById('order_status').value;

    var statusCheck = true;
    
    saveBtn.addEventListener('click', (event) => {
        if(statusCheck){
            event.preventDefault();
        }else return;

        if(!document.getElementById('financeId')){
            statusCheck = false;
            event.target.click();
            return;
        }

        const newStatus = document.getElementById('order_status').value;
        
        const financeId = document.getElementById('financeId').value;
        
        if(!financeId || newStatus === currentStatus || !checkStatuses.find((e) => e === newStatus)){
            statusCheck = false;
            event.target.click();
            return;
        }
        
        console.log("Making status check");
        // make ajax call
        const request = new XMLHttpRequest();
        request.onreadystatechange = () => {
            if(request.readyState === XMLHttpRequest.DONE && request.status === 200){
                const response = request.response;
                console.log(response);
                for(key in response.message){
                    let paragraph = document.createElement("p");
                    paragraph.innerText = response.message[key];
                    $("#financeStatusModal .contents").append(paragraph);
                }
                $('#financeStatusModal .contents').innerHTML = response.message;
                if(response.reasons != null){
                    const reasonSelect = document.createElement("select");
                    reasonSelect.setAttribute('id', 'pbdReason')
                    reasonSelect.classList.add('custom-select');
                    for(reason in response.reasons) {
                        let option = document.createElement("option");
                        option.text = response.reasons[reason]
                        option.value = reason
                        reasonSelect.add(option);
                    }
                    $("#financeStatusModal .contents").append(reasonSelect);
                }
                const continueBtn = {
                    text: response.action+" (without notifying lender)",
                    click: function(){
                        statusCheck = false;
                        event.target.click();
                        return;
                    }
                };
        
                let buttons = [continueBtn];

                $('#financeStatusModal')
                    .dialog({
                        buttons: buttons
                    })
                    .dialog('open');
            }
        }
        request.open("GET", statusCheckPath+'?action=woocommerce_finance_status&status='+newStatus+'&id='+financeId);
        request.responseType = "json";
        request.send();
            // on done either display a modal
            // or trigger click event
        
    })
    
})
    