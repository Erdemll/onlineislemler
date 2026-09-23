import SignaturePad from 'signature_pad';

document.querySelectorAll('[data-portal-menu-toggle]').forEach((toggle) => {
    const menu = document.getElementById(toggle.getAttribute('aria-controls'));

    if (!menu) {
        return;
    }

    const setOpen = (open) => {
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Menüyü kapat' : 'Menüyü aç');
        menu.classList.toggle('is-open', open);
        menu.inert = !open && window.matchMedia('(max-width: 1279px)').matches;
    };

    setOpen(false);
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        setOpen(open);

        if (open) {
            menu.querySelector('.portal-nav-link')?.focus();
        }
    });
    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target) && !toggle.contains(event.target)) {
            setOpen(false);
        }
    });
    window.matchMedia('(min-width: 1280px)').addEventListener('change', () => setOpen(false));
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

document.querySelectorAll('[data-product-sync-form]').forEach(async (form) => {
    const status = document.querySelector('[data-product-sync-status]');
    const message = status?.querySelector('[data-product-sync-message]');
    const catalog = document.querySelector('[data-product-catalog]');
    const url = new URL(window.location.href);
    const redirectParameter = form.dataset.productSyncRedirect;

    if (url.searchParams.has(redirectParameter)) {
        url.searchParams.delete(redirectParameter);
        window.history.replaceState({}, '', url);
        status?.classList.add('hidden');

        return;
    }

    catalog?.classList.add('opacity-50');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || 'Ürünler şu anda güncellenemedi.');
        }

        url.searchParams.set(redirectParameter, '1');
        window.location.replace(url);
    } catch (error) {
        catalog?.classList.remove('opacity-50');
        status?.querySelector('[aria-hidden="true"]')?.classList.add('hidden');

        if (message) {
            message.textContent = error.message;
        }
    }
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
