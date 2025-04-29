import AOS from "aos";
document.addEventListener("DOMContentLoaded", function () {
  const surveyContainer = document.getElementById("survey-container");
  const completionMessage = document.getElementById("completion-message");

  // Check if elements exist before using them
  if (!surveyContainer) {
    console.error("Error: survey-container element not found");
    return;
  }

  // Check if completion message exists, create it if not
  let completionMessageElement = completionMessage;
  if (!completionMessageElement) {
    console.warn("completion-message element not found, creating it");
    completionMessageElement = document.createElement("div");
    completionMessageElement.id = "completion-message";
    completionMessageElement.className = "success-message";
    completionMessageElement.innerHTML =
      "<h2>Thank you for your submission!</h2><p>We'll send your AdTech PoV document to your email shortly.</p>";
    completionMessageElement.style.display = "none";
    // Insert after survey container
    surveyContainer.parentNode.insertBefore(
      completionMessageElement,
      surveyContainer.nextSibling
    );
  }

  // Check if user has already completed the survey
  checkCompletion().then((completed) => {
    if (completed) {
      surveyContainer.style.display = "none";
      completionMessageElement.style.display = "block";
    } else {
      initializeSurvey(completionMessageElement);
    }
  });

  // Initialize AOS animation library if it's available
  try {
    if (AOS) {
      AOS.init({
        duration: 800,
        easing: "ease-in-out",
        once: true,
      });
    }
  } catch (error) {
    console.warn("AOS library not available:", error.message);
    // Load AOS from CDN if it's not available
    loadAOSfromCDN();
  }
});

function loadAOSfromCDN() {
  // Add AOS CSS
  const aosCSS = document.createElement("link");
  aosCSS.rel = "stylesheet";
  aosCSS.href = "https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css";
  document.head.appendChild(aosCSS);

  // Add AOS JS
  const aosScript = document.createElement("script");
  aosScript.src = "https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js";
  aosScript.onload = function () {
    if (window.AOS) {
      window.AOS.init({
        duration: 800,
        easing: "ease-in-out",
        once: true,
      });
    }
  };
  document.body.appendChild(aosScript);
}

async function checkCompletion() {
  try {
    // Use path relative to the web root instead of the current directory
    const response = await fetch("/api/check_completion.php", {
      method: "GET",
      headers: {
        Accept: "application/json",
        "Cache-Control": "no-cache",
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }

    const data = await response.json();
    console.log("Completion check response:", data);
    return data.completed;
  } catch (error) {
    console.error("Error checking completion status:", error);
    // In case of error, assume not completed so user can try the survey
    return false;
  }
}

function initializeSurvey(completionMessageElement) {
  const surveyContainer = document.getElementById("survey-container");
  if (!surveyContainer) {
    console.error("Error: survey-container element not found");
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

      // Use path relative to the web root instead of the current directory
      const response = await fetch("/api/submit_survey.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(formDataObj),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      const result = await response.json();
      console.log("Survey submission response:", result);

      if (result.success) {
        // Show completion message
        surveyContainer.style.display = "none";
        completionMessageElement.style.display = "block";

        // Scroll to completion message
        completionMessageElement.scrollIntoView({ behavior: "smooth" });
      } else {
        // Show error
        alert("Error: " + result.message);
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
