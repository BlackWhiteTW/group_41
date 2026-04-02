// 主要JavaScript文件

$(document).ready(function() {
    // 全局配置
    $.fn.dataTable.ext.settings = {
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/zh-hant.json'
        }
    };
    
    // 表單驗證函數
    window.validateForm = function(formId) {
        let isValid = true;
        $(`#${formId} input[required], #${formId} textarea[required], #${formId} select[required]`).each(function() {
            if (!$(this).val()) {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        return isValid;
    };
    
    // 移除無效樣式
    $('input, textarea, select').on('change input', function() {
        if ($(this).val()) {
            $(this).removeClass('is-invalid');
        }
    });
    
    // 全局AJAX錯誤處理
    $(document).ajaxError(function(event, jqxhr, settings, exception) {
        if (jqxhr.status === 401) {
            Swal.fire('未授權', '您已登出或沒有權限執行此操作', 'warning').then(() => {
                window.location.href = '/網際網路資料庫程式設計/login.php';
            });
        }
    });
});

// 工具函數
function showSuccessMessage(title, message) {
    Swal.fire(title, message, 'success');
}

function showErrorMessage(title, message) {
    Swal.fire(title, message, 'error');
}

function showWarningMessage(title, message) {
    Swal.fire(title, message, 'warning');
}

function confirmAction(title, message, callback) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '確認',
        cancelButtonText: '取消'
    }).then((result) => {
        if (result.isConfirmed && callback) {
            callback();
        }
    });
}
