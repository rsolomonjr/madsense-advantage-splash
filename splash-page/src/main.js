import "./style.css";

const API_BASE_URL = "https://madsense.tech/api";


// Survey questions
const questions = [
  {
    id: "question1",
    question: "Which AdTech challenge is your business currently facing?",
    type: "textarea",
  },
  {
    id: "question2",
    question: "What metrics are most important for your advertising campaigns?",
    type: "textarea",
  },
];

// State
let currentQuestion = 0;
let formData = {
  name: "",
  email: "",
  question1_answer: "",
  question2_answer: "",
};

// Check if user has already submitted
async function checkUserSubmission() {
  try {
  const response = await fetch(`${API_BASE_URL}/check_user.php`);
    const data = await response.json();

    if (data.has_submitted) {
      document.getElementById("survey-container").style.display = "none";
      document.getElementById("completion-message").style.display = "block";
    } else {
      renderSurveyForm();
    }
  } catch (error) {
    console.error("Error checking user submission:", error);
    renderSurveyForm();
  }
}

// Render the survey form
function renderSurveyForm() {
  const surveyContainer = document.getElementById("survey-container");

  // Create form element
  const form = document.createElement("form");
  form.className = "survey-form";
  form.id = "survey-form";
  form.onsubmit = handleFormSubmit;

  // Initial form fields (name and email)
  if (currentQuestion === 0) {
    form.innerHTML = `
      <div class="form-group">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required value="${
          formData.name
        }">
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="${
          formData.email
        }">
      </div>
      <div class="form-group">
        <label for="${questions[currentQuestion].id}">${
      questions[currentQuestion].question
    }</label>
        <textarea id="${questions[currentQuestion].id}" name="${
      questions[currentQuestion].id
    }" required>${
      formData[`${questions[currentQuestion].id}_answer`] || ""
    }</textarea>
      </div>
      <div id="nav-buttons">
        <button type="button" id="prev-button" disabled>Previous</button>
        <button type="button" id="next-button" class="button">Next</button>
      </div>
    `;
  }
  // Question 2
  else if (currentQuestion === 1) {
    form.innerHTML = `
      <div class="form-group">
        <label for="${questions[currentQuestion].id}">${
      questions[currentQuestion].question
    }</label>
        <textarea id="${questions[currentQuestion].id}" name="${
      questions[currentQuestion].id
    }" required>${
      formData[`${questions[currentQuestion].id}_answer`] || ""
    }</textarea>
      </div>
      <div id="nav-buttons">
        <button type="button" id="prev-button">Previous</button>
        <button type="submit" id="submit-button" class="button">Submit</button>
      </div>
    `;
  }

  surveyContainer.innerHTML = "";
  surveyContainer.appendChild(form);

  // Add event listeners
  if (currentQuestion === 0) {
    document
      .getElementById("next-button")
      .addEventListener("click", handleNextClick);
  } else {
    document
      .getElementById("prev-button")
      .addEventListener("click", handlePrevClick);
  }
}

// Handle form navigation
function handleNextClick() {
  // Save current form data
  formData.name = document.getElementById("name").value;
  formData.email = document.getElementById("email").value;
  formData[`${questions[currentQuestion].id}_answer`] = document.getElementById(
    questions[currentQuestion].id
  ).value;

  // Validate fields
  if (
    !formData.name ||
    !formData.email ||
    !formData[`${questions[currentQuestion].id}_answer`]
  ) {
    alert("Please fill out all fields");
    return;
  }

  // Move to next question
  currentQuestion++;
  renderSurveyForm();
}

function handlePrevClick() {
  // Save current form data
  formData[`${questions[currentQuestion].id}_answer`] = document.getElementById(
    questions[currentQuestion].id
  ).value;

  // Move to previous question
  currentQuestion--;
  renderSurveyForm();
}

// Handle form submission
async function handleFormSubmit(event) {
  event.preventDefault();

  // Save the last question's answer
  formData[`${questions[currentQuestion].id}_answer`] = document.getElementById(
    questions[currentQuestion].id
  ).value;

  // Validate all fields
  if (
    !formData.name ||
    !formData.email ||
    !formData.question1_answer ||
    !formData.question2_answer
  ) {
    alert("Please fill out all fields");
    return;
  }

  try {
    const response = await fetch(`${API_BASE_URL}/submit_survey.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(formData),
    });

    const result = await response.json();

    if (result.success) {
      // Show completion message
      document.getElementById("survey-container").style.display = "none";
      document.getElementById("completion-message").style.display = "block";
    } else {
      alert(result.message || "There was an error submitting your survey");
    }
  } catch (error) {
    console.error("Error submitting survey:", error);
    alert("There was an error submitting your survey. Please try again.");
  }
}

// Initialize the app
document.addEventListener("DOMContentLoaded", checkUserSubmission);
