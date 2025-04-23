const { MongoClient } = require("mongodb");

// Replace with your MongoDB connection string
const uri = process.env.MONGODB_URI;

exports.handler = async (event, context) => {
  if (event.httpMethod !== "POST") {
    return { statusCode: 405, body: "Method Not Allowed" };
  }

  // Get IP address from request
  const ip = event.headers["x-forwarded-for"] || event.headers["client-ip"];

  try {
    const data = JSON.parse(event.body);

    // Validate required fields
    if (
      !data.name ||
      !data.email ||
      !data.question1_answer ||
      !data.question2_answer
    ) {
      return {
        statusCode: 400,
        body: JSON.stringify({
          success: false,
          message: "Missing required fields",
        }),
      };
    }

    const client = new MongoClient(uri);

    try {
      await client.connect();
      const database = client.db("splash_survey");
      const collection = database.collection("survey_responses");

      // Check if IP exists in database
      const count = await collection.countDocuments({ ip_address: ip });

      if (count > 0) {
        return {
          statusCode: 409,
          body: JSON.stringify({
            success: false,
            message: "You have already submitted a response",
          }),
        };
      }

      // Insert response
      const result = await collection.insertOne({
        name: data.name,
        email: data.email,
        ip_address: ip,
        question1_answer: data.question1_answer,
        question2_answer: data.question2_answer,
        created_at: new Date(),
      });

      return {
        statusCode: 200,
        body: JSON.stringify({
          success: true,
          message: "Survey submitted successfully",
        }),
      };
    } finally {
      await client.close();
    }
  } catch (error) {
    return {
      statusCode: 500,
      body: JSON.stringify({ success: false, message: error.message }),
    };
  }
};
