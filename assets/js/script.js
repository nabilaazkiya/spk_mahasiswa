const bobotInputs = document.querySelectorAll('.bobot-input');
const totalBobot = document.getElementById('totalBobot');

function hitungTotalBobot() {
    let total = 0;

    bobotInputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });

    totalBobot.textContent = total.toFixed(2);
}

bobotInputs.forEach(input => {
    input.addEventListener('input', hitungTotalBobot);
});

hitungTotalBobot();