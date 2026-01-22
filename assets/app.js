document.addEventListener('DOMContentLoaded', () => {
    const cardSelect = document.querySelector('[data-ajax-card]');
    const infoBox = document.getElementById('card-info');

    if (cardSelect && infoBox) {
        cardSelect.addEventListener('change', async () => {
            const cardId = cardSelect.value;
            if (!cardId) {
                infoBox.innerHTML = '<strong>Информация по карте:</strong><p>Выберите карту, чтобы увидеть банк-эмитент и баланс счёта.</p>';
                return;
            }

            try {
                const response = await fetch(`ajax/card_info.php?card_id=${encodeURIComponent(cardId)}`);
                const data = await response.json();

                if (data.error) {
                    infoBox.innerHTML = `<strong>Ошибка:</strong> ${data.error}`;
                    return;
                }

                infoBox.innerHTML = `
                    <strong>Информация по карте:</strong>
                    <p>Клиент: ${data.full_name}</p>
                    <p>Банк-эмитент: ${data.issuing_bank}</p>
                    <p>Баланс: ${data.balance} ${data.currency}</p>
                `;
            } catch (error) {
                infoBox.textContent = 'Не удалось загрузить данные по карте.';
            }
        });
    }

    const withdrawForm = document.querySelector('[data-withdraw-form]');
    if (withdrawForm && withdrawForm.querySelector('[name="amount"]')) {
        withdrawForm.addEventListener('submit', (event) => {
            const amountInput = withdrawForm.querySelector('[name="amount"]');
            if (amountInput && Number(amountInput.value) <= 0) {
                event.preventDefault();
                alert('Сумма должна быть больше 0.');
            }
        });
    }
});
