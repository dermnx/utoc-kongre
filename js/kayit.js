(function () {
    const form = document.getElementById('kayitForm');
    if (!form) {
        return;
    }

    const feedbackEl = form.querySelector('.form-feedback');
    const workshopInputs = Array.from(form.querySelectorAll('.workshop-checkbox'));
    const discountInputs = Array.from(form.querySelectorAll('input[name="discounts[]"]'));
    const formatInputs = Array.from(form.querySelectorAll('input[name="format"]'));
    const fileInputs = Array.from(form.querySelectorAll('input[type="file"][data-max-size]'));
    const paymentInput = document.getElementById('payment_amount');
    const summaryFields = {
        format: form.querySelector('[data-total-format]'),
        workshops: form.querySelector('[data-total-workshops]'),
        discount: form.querySelector('[data-total-discount]'),
        payable: form.querySelector('[data-total-payable]'),
    };

    const PRICE_TABLE = {
        format: {
            'yuz-yuze': 6500,
            online: 3500,
        },
        workshop: {
            experiential: 3500,
            clinical: 5000,
        },
        discountStep: 0.1,
    };

    const setFeedback = (message, isError = false) => {
        if (!feedbackEl) return;
        feedbackEl.textContent = message;
        feedbackEl.classList.toggle('form-feedback--error', isError);
        feedbackEl.classList.toggle('form-feedback--success', !isError);
    };

    const formatCurrency = (value) => {
        if (!value || Number.isNaN(value)) {
            return '0 TL';
        }
        return new Intl.NumberFormat('tr-TR', {
            minimumFractionDigits: 0,
        }).format(Math.round(value)) + ' TL';
    };

    const updateSummaryFields = (details) => {
        if (!details) return;
        const { formatTotal, workshopTotal, discountAmount, payable } = details;
        if (summaryFields.format) summaryFields.format.textContent = formatCurrency(formatTotal);
        if (summaryFields.workshops) summaryFields.workshops.textContent = formatCurrency(workshopTotal);
        if (summaryFields.discount) summaryFields.discount.textContent = formatCurrency(discountAmount);
        if (summaryFields.payable) summaryFields.payable.textContent = formatCurrency(payable);
        if (paymentInput) {
            paymentInput.value = payable > 0 ? formatCurrency(payable) : '';
            paymentInput.dataset.numericValue = payable > 0 ? payable : '';
        }
    };

    const calculateTotals = () => {
        const selectedFormat = form.querySelector('input[name="format"]:checked');
        const formatPrice = selectedFormat ? (PRICE_TABLE.format[selectedFormat.value] || 0) : 0;

        const workshopPrice = workshopInputs.reduce((sum, input) => {
            if (!input.checked) return sum;
            const type = input.dataset.feeType;
            const fee = PRICE_TABLE.workshop[type] || 0;
            return sum + fee;
        }, 0);

        const subtotal = formatPrice + workshopPrice;
        const selectedDiscountCount = discountInputs.filter((input) => input.checked).length;
        const discountMultiplier = Math.max(0, 1 - (selectedDiscountCount * PRICE_TABLE.discountStep));
        const payable = Math.round(subtotal * discountMultiplier);
        const discountAmount = Math.max(0, subtotal - payable);

        return {
            formatTotal: formatPrice,
            workshopTotal: workshopPrice,
            discountAmount,
            payable,
        };
    };

    const updateTotals = () => {
        const details = calculateTotals();
        updateSummaryFields(details);
    };

    const syncRequiredFiles = () => {
        discountInputs.forEach((checkbox) => {
            const targetId = checkbox.dataset.requires;
            if (!targetId) return;
            const fileInput = document.getElementById(targetId);
            if (!fileInput) return;

            if (checkbox.checked) {
                fileInput.setAttribute('required', 'required');
                fileInput.parentElement.classList.add('is-required');
            } else {
                fileInput.removeAttribute('required');
                fileInput.parentElement.classList.remove('is-required');
                fileInput.setCustomValidity('');
            }
        });
    };

    discountInputs.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            syncRequiredFiles();
            updateTotals();
        });
    });
    syncRequiredFiles();

    const enforceTimeSlots = (changedInput) => {
        const slot = changedInput.dataset.slot;
        if (!slot || !changedInput.checked) return;

        workshopInputs.forEach((input) => {
            if (input === changedInput) return;
            if (input.dataset.slot === slot && input.checked) {
                input.checked = false;
            }
        });
    };

    workshopInputs.forEach((input) => {
        input.addEventListener('change', () => {
            const selectedFormat = form.querySelector('input[name="format"]:checked');
            if (!selectedFormat) {
                input.checked = false;
                setFeedback('Atölye seçebilmek için önce kongre formatını seçiniz.', true);
                if (formatInputs[0]) {
                    formatInputs[0].focus();
                }
                return;
            }
            enforceTimeSlots(input);
            updateTotals();
        });
    });

    formatInputs.forEach((input) => {
        input.addEventListener('change', () => {
            updateTotals();
        });
    });

    const validateFileInput = (input) => {
        const file = input.files && input.files[0];
        if (!file) {
            input.setCustomValidity('');
            return true;
        }
        const maxSize = parseInt(input.dataset.maxSize, 10) || Infinity;
        const allowedTypes = (input.accept || '').split(',').map((t) => t.trim()).filter(Boolean);

        if (file.size > maxSize) {
            const sizeMb = (maxSize / (1024 * 1024)).toFixed(1);
            input.value = '';
            input.setCustomValidity(`Dosya boyutu ${sizeMb} MB sınırını aşamaz.`);
            input.reportValidity();
            return false;
        }

        if (allowedTypes.length && !allowedTypes.includes(file.type)) {
            input.value = '';
            input.setCustomValidity('Lütfen PDF formatında dosya yükleyiniz.');
            input.reportValidity();
            return false;
        }

        input.setCustomValidity('');
        return true;
    };

    fileInputs.forEach((input) => {
        input.addEventListener('change', () => validateFileInput(input));
    });

    const ensureCalculatedTotal = () => {
        if (!paymentInput) return false;
        const numericValue = Number(paymentInput.dataset.numericValue || 0);
        if (!numericValue) {
            updateTotals();
            return Number(paymentInput.dataset.numericValue || 0) > 0;
        }
        return true;
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            setFeedback('Lütfen zorunlu alanları doldurunuz.', true);
            return;
        }
        if (!ensureCalculatedTotal()) {
            setFeedback('Lütfen format ve atölye seçimlerinizi yaparak toplam tutarı oluşturunuz.', true);
            return;
        }
        setFeedback('Form gönderiliyor, lütfen bekleyin...');

        // Final file validation before submit
        for (const input of fileInputs) {
            if (!validateFileInput(input)) {
                setFeedback('Lütfen dosya yükleme kurallarını kontrol ediniz.', true);
                return;
            }
        }

        const formData = new FormData(form);
        if (paymentInput && paymentInput.dataset.numericValue) {
            formData.set('payment_amount', paymentInput.dataset.numericValue);
        }

        fetch(form.action, {
            method: 'POST',
            body: formData,
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const errorMessage = payload.message || 'Form gönderilirken bir hata oluştu.';
                    throw new Error(errorMessage);
                }
                return payload;
            })
            .then((data) => {
                setFeedback(data.message || 'Başvurunuz başarıyla alındı.');
                if (typeof data.total_amount === 'number') {
                    const currentDetails = calculateTotals();
                    const subtotal = currentDetails.formatTotal + currentDetails.workshopTotal;
                    updateSummaryFields({
                        ...currentDetails,
                        payable: data.total_amount,
                        discountAmount: Math.max(0, subtotal - data.total_amount),
                    });
                }
                form.reset();
                syncRequiredFiles();
                updateTotals();
            })
            .catch((error) => {
                setFeedback(error.message, true);
            });
    });

    updateTotals();
})();
