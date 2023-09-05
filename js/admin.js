

const checkStatuses = ['wc-cancelled', 'wc-refunded'];

jQuery(document).ready(function($) {
    $('#financeStatusModal').dialog({
        autoOpen:false, 
        draggable:false,
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
                const checkRes = request.response;

                if(checkRes.bypass === true){
                    statusCheck = false;
                    event.target.click();
                    return;
                }

                $('#financeStatusModal .contents').text = "";
                if(typeof(checkRes.message) == "object"){
                    for(key in checkRes.message){
                        let paragraph = document.createElement("p");
                        paragraph.innerText = checkRes.message[key];
                        $("#financeStatusModal .contents").append(paragraph);
                    }
                }
                
                if(checkRes.reasons != null){
                    const reasonSelect = document.createElement("select");
                    reasonSelect.setAttribute('id', 'pbdReason')
                    reasonSelect.classList.add('custom-select');
                    for(reason in checkRes.reasons) {
                        let option = document.createElement("option");
                        option.text = checkRes.reasons[reason]
                        option.value = reason
                        reasonSelect.add(option);
                    }
                    $("#financeStatusModal .contents").append(reasonSelect);
                }
                const continueBtn = {
                    text: checkRes.action+" (without notifying lender)",
                    click: function(){
                        statusCheck = false;
                        event.target.click();
                        return;
                    }
                };
        
                let buttons = [continueBtn];

                if(checkRes.notify){
                    buttons.push({
                        text: checkRes.action+" and notify lender",
                        click: function(){
                            var updateUrl = 'admin-ajax.php';
                            $.ajax({
                                url: updateUrl,
                                type: 'GET',
                                data: {
                                    action: 'woocommerce_finance_status-update',
                                    wf_action: checkRes.action,
                                    application_id: financeId,
                                    reason: (document.getElementById('pbdReason'))
                                        ? $('#pbdReason').val()
                                        : null
                                }
                            }).done(function(updateRes){
                                $("#financeStatusModal .contents")
                                    .html("<p>"+updateRes.message+"</p>");
                                if(updateRes.success === false) {
                                    let newBtns = [];
                                    newBtns.push(continueBtn);
                                    $('#financeStatusModal').dialog({buttons:newBtns});
                                } else {
                                    statusCheck = false;
                                    event.target.click();
                                    return;
                                }
                            });
                        }
                    });
                }

                $('#financeStatusModal')
                    .dialog({
                        title: checkRes.title,
                        buttons: buttons
                    })
                    .dialog('open');
            }
        }
        request.open("GET", statusCheckPath+'?action=woocommerce_finance_status-check&status='+newStatus+'&id='+financeId);
        request.responseType = "json";
        request.send();
            // on done either display a modal
            // or trigger click event
        
    })
    
})
    