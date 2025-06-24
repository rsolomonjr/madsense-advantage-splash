// import AOS from "aos";
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

  // Initialize the survey (no longer checking for completion)
  initializeSurvey(completionMessageElement);

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

async function checkTopicCompletion(email, topic) {
  try {
    const response = await fetch(`api/check_completion.php?email=${encodeURIComponent(email)}&topic=${encodeURIComponent(topic)}`, {
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
    console.log("Topic completion check response:", data);
    return data.completed;
  } catch (error) {
    console.error("Error checking topic completion status:", error);
    // In case of error, assume not completed so user can try the survey
    return false;
  }
}

async function getUserCompletedTopics(email) {
  try {
    const response = await fetch(`api/check_completion.php?email=${encodeURIComponent(email)}&get_topics=1`, {
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
    console.log("User completed topics response:", data);
    return data.completed_topics || [];
  } catch (error) {
    console.error("Error getting user completed topics:", error);
    return [];
  }
}

// Global variable to store reCAPTCHA widget ID
let recaptchaWidgetId = null;

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
    { value: "analytics", text: "Analytics" },
    { value: "privacy", text: "Privacy Regulations" },
    { value: "ai", text: "AI for Marketers" },
  ];

  topics.forEach((topic) => {
    const option = document.createElement("option");
    option.value = topic.value;
    option.textContent = topic.text;
    if (topic.disabled) option.disabled = true;
    if (topic.selected) option.selected = true;
    q1Select.appendChild(option);
  });

  // Get URL parameters to pre-select topic if available
  const urlParams = new URLSearchParams(window.location.search);
  const topicParam = urlParams.get("topic");
  if (topicParam) {
    // Find the option that matches our parameter and select it
    for (let i = 0; i < q1Select.options.length; i++) {
      if (q1Select.options[i].value === topicParam) {
        q1Select.options[i].selected = true;
        // Make sure the default "Select an option" is not selected
        if (q1Select.options[0].disabled) {
          q1Select.options[0].selected = false;
        }
        break;
      }
    }
  }

  q1Group.appendChild(q1Select);
  questionsSection.appendChild(q1Group);

  // Add warning message container for already requested topics
  const warningContainer = document.createElement("div");
  warningContainer.id = "topic-warning";
  warningContainer.style.display = "none";
  warningContainer.style.color = "#e74c3c";
  warningContainer.style.padding = "10px";
  warningContainer.style.marginTop = "10px";
  warningContainer.style.border = "1px solid #e74c3c";
  warningContainer.style.borderRadius = "4px";
  warningContainer.style.backgroundColor = "#fdf2f2";
  q1Group.appendChild(warningContainer);

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

  // Add reCAPTCHA container
  const recaptchaGroup = document.createElement("div");
  recaptchaGroup.className = "form-group";
  
  const recaptchaContainer = document.createElement("div");
  recaptchaContainer.id = "recaptcha-container";
  recaptchaContainer.style.marginBottom = "20px";
  
  recaptchaGroup.appendChild(recaptchaContainer);
  form.appendChild(recaptchaGroup);

  // Submit button
  const submitButton = document.createElement("button");
  submitButton.type = "submit";
  submitButton.className = "button";
  submitButton.textContent = "Send Me My Free PoV";
  form.appendChild(submitButton);

  // Add form to container
  surveyContainer.appendChild(form);

  // Initialize reCAPTCHA when the script is loaded
  const initRecaptcha = () => {
    if (typeof grecaptcha !== 'undefined' && grecaptcha.render) {
      try {
        recaptchaWidgetId = grecaptcha.render('recaptcha-container', {
          'sitekey': '6Lc8FE4rAAAAAPo-GBbwvwgd9GlDO8OW58nqld-F', // Replace with your actual site key
          'theme': 'light',
          'size': 'normal'
        });
        console.log('reCAPTCHA initialized with widget ID:', recaptchaWidgetId);
      } catch (error) {
        console.error('Error initializing reCAPTCHA:', error);
      }
    } else {
      // If grecaptcha is not ready yet, try again in 100ms
      setTimeout(initRecaptcha, 100);
    }
  };

  // Start trying to initialize reCAPTCHA
  initRecaptcha();

  // Email field change handler to check completed topics
  const emailInput = document.getElementById("email");
  emailInput.addEventListener("blur", async function() {
    const email = this.value.trim();
    if (email && emailInput.checkValidity()) {
      const completedTopics = await getUserCompletedTopics(email);
      updateTopicOptions(q1Select, completedTopics);
    }
  });

  // Topic selection change handler
  q1Select.addEventListener("change", async function() {
    const selectedTopic = this.value;
    const email = emailInput.value.trim();
    const warningContainer = document.getElementById("topic-warning");
    
    if (email && selectedTopic && emailInput.checkValidity()) {
      const alreadyCompleted = await checkTopicCompletion(email, selectedTopic);
      if (alreadyCompleted) {
        warningContainer.innerHTML = "You have already requested a PoV for this topic. Please select a different topic to receive a new PoV.";
        warningContainer.style.display = "block";
        submitButton.disabled = true;
      } else {
        warningContainer.style.display = "none";
        submitButton.disabled = false;
      }
    } else {
      warningContainer.style.display = "none";
      submitButton.disabled = false;
    }
  });

  // Form submission handler
  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    // Validate reCAPTCHA
    if (typeof grecaptcha !== 'undefined' && recaptchaWidgetId !== null) {
      const recaptchaResponse = grecaptcha.getResponse(recaptchaWidgetId);
      if (!recaptchaResponse) {
        alert("Please complete the reCAPTCHA verification.");
        return;
      }
    } else {
      console.warn("reCAPTCHA not initialized - proceeding without verification");
    }

    const formData = new FormData(form);
    const formDataObj = {};
    formData.forEach((value, key) => {
      formDataObj[key] = value;
    });

    // Add reCAPTCHA response to form data
    if (typeof grecaptcha !== 'undefined' && recaptchaWidgetId !== null) {
      formDataObj['recaptcha_response'] = grecaptcha.getResponse(recaptchaWidgetId);
    }

    // Double-check if topic was already requested
    const alreadyCompleted = await checkTopicCompletion(formDataObj.email, formDataObj.question1_answer);
    if (alreadyCompleted) {
      alert("You have already requested a PoV for this topic. Please select a different topic.");
      return;
    }

    try {
      // Display loading state
      submitButton.disabled = true;
      submitButton.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Sending...';

      const response = await fetch("api/submit_survey.php", {
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

      let result;
      try {
        const responseText = await response.text();
        let jsonStartIndex = responseText.indexOf("{");
        if (jsonStartIndex === -1) {
          throw new Error("No JSON object found in response");
        }

        let jsonText = responseText.substring(jsonStartIndex);
        let jsonEndIndex = jsonText.lastIndexOf("}");
        if (jsonEndIndex === -1) {
          throw new Error("Malformed JSON response");
        }

        jsonText = jsonText.substring(0, jsonEndIndex + 1);
        result = JSON.parse(jsonText);
        console.log("Parsed response:", result);
      } catch (parseError) {
        console.error("Error parsing JSON:", parseError);
        console.log("Raw response received:", await response.text());
        throw new Error("Invalid JSON response from server");
      }

      if (result && result.success) {
        // Show completion message
        surveyContainer.style.display = "none";
        completionMessageElement.style.display = "block";

        // Scroll to completion message
        completionMessageElement.scrollIntoView({ behavior: "smooth" });
      } else {
        alert(
          "Error: " +
            (result && result.message ? result.message : "Unknown error")
        );
        
        // Reset reCAPTCHA on error
        if (typeof grecaptcha !== 'undefined' && recaptchaWidgetId !== null) {
          grecaptcha.reset(recaptchaWidgetId);
        }
        
        submitButton.disabled = false;
        submitButton.textContent = "Send Me My Free PoV";
      }
    } catch (error) {
      console.error("Error submitting survey:", error);
      alert("An error occurred. Please try again.");
      
      // Reset reCAPTCHA on error
      if (typeof grecaptcha !== 'undefined' && recaptchaWidgetId !== null) {
        grecaptcha.reset(recaptchaWidgetId);
      }
      
      submitButton.disabled = false;
      submitButton.textContent = "Send Me My Free PoV";
    }
  });
}

function updateTopicOptions(selectElement, completedTopics) {
  const options = selectElement.querySelectorAll("option");
  options.forEach(option => {
    if (option.value && completedTopics.includes(option.value)) {
      option.textContent = option.textContent + " (Already Requested)";
      option.style.color = "#999";
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