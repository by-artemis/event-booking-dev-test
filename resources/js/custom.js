const datepicker = document.getElementById("booking_date");
const restrictMessage = document.getElementById("restrict_message");
const closeButton  = document.getElementById("close_restrict_message");

datepicker.addEventListener("change", (event) => {
  const selectedDate = new Date(event.target.value);
  const dayOfWeek = selectedDate.getDay();

  if (dayOfWeek === 0 || dayOfWeek === 6) { // 0 = Sunday, 6 = Saturday
    event.target.value = ""; // Clear the selected date if it's a weekend

    restrictMessage.classList.remove("hidden");

    setTimeout(() => {
      restrictMessage.classList.add("hidden");
    }, 10000); // Display for 10 seconds
  } else {
    restrictMessage.classList.add("hidden");
    submitForm(); // Submit the form if a weekday is selected
  }
});

closeButton.addEventListener("click", () => {
  restrictMessage.classList.add("hidden");
});

function submitForm() {
  document.getElementById("booking_form").submit();
}
