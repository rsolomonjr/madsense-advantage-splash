import AOS from "aos";

document.addEventListener("DOMContentLoaded", function () {
  const surveyContainer = document.getElementById("survey-container");
  const completionMessage = document.getElementById("completion-message");

  // Check if the completion message element exists
  if (!completionMessage) {
    console.error("Error: Could not find element with ID 'completion-message'");
    // Create the completion message element if it doesn't exist
    createCompletionMessage();
  }

  // Check if user has already completed the survey
  checkCompletion().then((completed) => {
    const updatedCompletionMessage =
      document.getElementById("completion-message");

    if (completed) {
      if (surveyContainer) {
        surveyContainer.style.display = "none";
      }
      if (updatedCompletionMessage) {
        updatedCompletionMessage.style.display = "block";
      }
    } else {
      initializeSurvey();
    }
  });

  // Initialize AOS animation library if it's loaded
  if (typeof AOS !== "undefined") {
    AOS.init({
      duration: 800,
      easing: "ease-in-out",
      once: true,
    });
  }
});

// Create completion message if it doesn't exist
function createCompletionMessage() {
  const mainContainer =
    document.getElementById("survey-container")?.parentNode || document.body;

  const messageDiv = document.createElement("div");
  messageDiv.id = "completion-message";
  messageDiv.className = "thank-you-message";
  messageDiv.style.display = "none";

  // Create content for the completion message
  const heading = document.createElement("h2");
  heading.textContent = "Thank You for Your Submission!";

  const message = document.createElement("p");
  message.textContent =
    "We'll send your free Point of View document to your email shortly.";

  // Append elements
  messageDiv.appendChild(heading);
  messageDiv.appendChild(message);
  mainContainer.appendChild(messageDiv);

  return messageDiv;
}

async function checkCompletion() {
  try {
    // Update this to use your serverless function instead of PHP
    const response = await fetch("/.netlify/functions/check-user");
    if (!response.ok) {
      throw new Error(`HTTP error: ${response.status}`);
    }
    const data = await response.json();
    return data.has_submitted; // Note: changed from data.completed to data.has_submitted to match your serverless function
  } catch (error) {
    console.error("Error checking completion status:", error);
    return false;
  }
}

function initializeSurvey() {
  const surveyContainer = document.getElementById("survey-container");

  if (!surveyContainer) {
    console.error("Error: Could not find element with ID 'survey-container'");
    return;
  }

  // Create survey form
  const form = document.createElement("form");
  form.className = "survey-form";
  form.setAttribute("id", "povSurvey");

  // Personal information section
  const personalInfoSection = document.createElement("div");
  personalInfoSection.className = "form-section";

  // Full Name field
  const nameGroup = createFormGroup("name", "Full Name", "text", true);
  personalInfoSection.appendChild(nameGroup);

  // Email field
  const emailGroup = createFormGroup("email", "Email Address", "email", true);
  personalInfoSection.appendChild(emailGroup);

  // Company field
  const companyGroup = createFormGroup("company", "Company Name", "text", true);
  personalInfoSection.appendChild(companyGroup);

  // Job Title field
  const titleGroup = createFormGroup("jobTitle", "Job Title", "text", true);
  personalInfoSection.appendChild(titleGroup);

  form.appendChild(personalInfoSection);

  // Survey questions section
  const questionsSection = document.createElement("div");
  questionsSection.className = "form-section";

  // Question 1
  const q1Group = document.createElement("div");
  q1Group.className = "form-group";

  const q1Label = document.createElement("label");
  q1Label.textContent =
    "Which AdTech topic are you most interested in receiving a PoV about?";
  q1Label.setAttribute("for", "question1_answer");
  q1Group.appendChild(q1Label);

  const q1Select = document.createElement("select");
  q1Select.id = "question1_answer";
  q1Select.name = "question1_answer";
  q1Select.required = true;

  const topics = [
    { value: "", text: "Select an option", disabled: true, selected: true },
    { value: "programmatic", text: "Programmatic Advertising" },
    { value: "identities", text: "Identity Resolution" },
    { value: "cookies", text: "Cookieless Targeting" },
    { value: "attribution", text: "Multi-touch Attribution" },
    { value: "privacy", text: "Privacy Regulations" },
    { value: "ai", text: "AI in Advertising" },
  ];

  topics.forEach((topic) => {
    const option = document.createElement("option");
    option.value = topic.value;
    option.textContent = topic.text;
    if (topic.disabled) option.disabled = true;
    if (topic.selected) option.selected = true;
    q1Select.appendChild(option);
  });

  q1Group.appendChild(q1Select);
  questionsSection.appendChild(q1Group);

  // Question 2
  const q2Group = document.createElement("div");
  q2Group.className = "form-group";

  const q2Label = document.createElement("label");
  q2Label.textContent =
    "What is your biggest challenge with marketing technology?";
  q2Label.setAttribute("for", "question2_answer");
  q2Group.appendChild(q2Label);

  const q2Textarea = document.createElement("textarea");
  q2Textarea.id = "question2_answer";
  q2Textarea.name = "question2_answer";
  q2Textarea.rows = 4;
  q2Textarea.required = true;
  q2Group.appendChild(q2Textarea);

  questionsSection.appendChild(q2Group);
  form.appendChild(questionsSection);

  // Submit button
  const submitButton = document.createElement("button");
  submitButton.type = "submit";
  submitButton.className = "button";
  submitButton.textContent = "Send Me My Free PoV";
  form.appendChild(submitButton);

  // Add form to container
  surveyContainer.appendChild(form);

  // Form submission handler
  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    const formData = new FormData(form);
    const formDataObj = {};
    formData.forEach((value, key) => {
      formDataObj[key] = value;
    });

    try {
      // Display loading state
      submitButton.disabled = true;
      submitButton.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Sending...';

      // Update to use your serverless function instead of PHP
      const response = await fetch("/.netlify/functions/submit-survey", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(formDataObj),
      });

      if (!response.ok) {
        throw new Error(`HTTP error: ${response.status}`);
      }

      const result = await response.json();

      if (result.success) {
        // Show completion message
        surveyContainer.style.display = "none";
        const completionMessage = document.getElementById("completion-message");
        if (completionMessage) {
          completionMessage.style.display = "block";
          // Scroll to completion message
          completionMessage.scrollIntoView({ behavior: "smooth" });
        } else {
          console.error("Completion message element not found");
          alert("Thank you for your submission!");
        }
      } else {
        // Show error
        alert("Error: " + (result.message || "Unknown error"));
        submitButton.disabled = false;
        submitButton.textContent = "Send Me My Free PoV";
      }
    } catch (error) {
      console.error("Error submitting survey:", error);
      alert("An error occurred. Please try again.");
      submitButton.disabled = false;
      submitButton.textContent = "Send Me My Free PoV";
    }
  });
}

function createFormGroup(id, labelText, type, required = false) {
  const group = document.createElement("div");
  group.className = "form-group";

  const label = document.createElement("label");
  label.setAttribute("for", id);
  label.textContent = labelText;

  const input = document.createElement("input");
  input.type = type;
  input.id = id;
  input.name = id;
  if (required) input.required = true;

  group.appendChild(label);
  group.appendChild(input);

  return group;
}
