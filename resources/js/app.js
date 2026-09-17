import SignaturePad from 'signature_pad';

document.querySelectorAll('[data-customer-navigation]').forEach((navigation) => {
    const activeItem = navigation.querySelector('[data-customer-navigation-active]');
    const wrapper = navigation.parentElement;
    const startHint = wrapper.querySelector('[data-customer-navigation-start]');
    const endHint = wrapper.querySelector('[data-customer-navigation-end]');

    const updateHints = () => {
        const maximumScroll = navigation.scrollWidth - navigation.clientWidth;

        startHint.classList.toggle('opacity-0', navigation.scrollLeft <= 4);
        endHint.classList.toggle('opacity-0', maximumScroll <= 4 || navigation.scrollLeft >= maximumScroll - 4);
    };

    const revealActiveItem = () => {
        if (!activeItem) {
            updateHints();

            return;
        }

        const centeredPosition = activeItem.offsetLeft - ((navigation.clientWidth - activeItem.offsetWidth) / 2);
        const maximumScroll = navigation.scrollWidth - navigation.clientWidth;
        navigation.scrollLeft = Math.min(Math.max(centeredPosition, 0), maximumScroll);
        updateHints();
    };

    navigation.addEventListener('scroll', updateHints, { passive: true });
    window.addEventListener('resize', revealActiveItem);
    requestAnimationFrame(revealActiveItem);
});

document.querySelectorAll('[data-admin-contract-form]').forEach((form) => {
    const syncContractMode = () => {
        const selectedMode = form.querySelector('[name="contract_mode"]:checked')?.value;

        form.querySelectorAll('[data-contract-mode]').forEach((section) => {
            const isActive = section.dataset.contractMode === selectedMode;
            section.hidden = !isActive;
            section.querySelectorAll('input, select').forEach((control) => {
                control.disabled = !isActive;
                control.required = isActive;
            });
        });
    };

    form.querySelectorAll('[name="contract_mode"]').forEach((radio) => {
        radio.addEventListener('change', syncContractMode);
    });
    syncContractMode();
});

document.querySelectorAll('[data-signature-form]').forEach((form) => {
    const canvas = form.querySelector('[data-signature-canvas]');
    const hiddenInput = form.querySelector('[data-signature-data]');
    const clearButton = form.querySelector('[data-signature-clear]');
    const error = form.querySelector('[data-signature-error]');
    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(15, 23, 42)',
        minWidth: 0.8,
        maxWidth: 2.8,
    });

    const resizeCanvas = () => {
        const points = signaturePad.toData();
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        signaturePad.clear();

        if (points.length > 0) {
            signaturePad.fromData(points);
        }
    };

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    clearButton.addEventListener('click', () => {
        signaturePad.clear();
        hiddenInput.value = '';
        error.classList.add('hidden');
    });

    form.addEventListener('submit', (event) => {
        if (signaturePad.isEmpty()) {
            event.preventDefault();
            error.classList.remove('hidden');
            canvas.focus();

            return;
        }

        hiddenInput.value = signaturePad.toDataURL('image/png');
        error.classList.add('hidden');
    });
});
