/* eslint-disable no-undef */
const { MongoClient } = require("mongodb");

// MongoDB connection string from environment variable
const uri = process.env.MONGODB_URI;

// Create a MongoDB client instance outside the handler for connection reuse
let cachedClient = null;
let cachedDb = null;

// Function to connect to database
async function connectToDatabase() {
  if (cachedClient && cachedDb) {
    // Use cached connection if available
    return { client: cachedClient, db: cachedDb };
  }

  // Create new connection
  const client = new MongoClient(uri, {
    useNewUrlParser: true,
    useUnifiedTopology: true,
  });

  await client.connect();
  const db = client.db("splash_survey");

  // Cache connection
  cachedClient = client;
  cachedDb = db;

  return { client, db };
}

// Export the handler function correctly
module.exports.handler = async (event, context) => {
  // Set context.callbackWaitsForEmptyEventLoop to false to keep the connection alive
  context.callbackWaitsForEmptyEventLoop = false;

  if (event.httpMethod !== "POST") {
    return {
      statusCode: 405,
      headers: {
        "Content-Type": "application/json",
        "Access-Control-Allow-Origin": "*", // Adjust as needed for production
      },
      body: JSON.stringify({ success: false, message: "Method Not Allowed" }),
    };
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
        headers: {
          "Content-Type": "application/json",
          "Access-Control-Allow-Origin": "*", // Adjust as needed for production
        },
        body: JSON.stringify({
          success: false,
          message: "Missing required fields",
        }),
      };
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email)) {
      return {
        statusCode: 400,
        headers: {
          "Content-Type": "application/json",
          "Access-Control-Allow-Origin": "*",
        },
        body: JSON.stringify({
          success: false,
          message: "Invalid email format",
        }),
      };
    }

    const { db } = await connectToDatabase();
    const collection = db.collection("survey_responses");

    // Check if IP exists in database
    const count = await collection.countDocuments({ ip_address: ip });

    if (count > 0) {
      return {
        statusCode: 409,
        headers: {
          "Content-Type": "application/json",
          "Access-Control-Allow-Origin": "*",
        },
        body: JSON.stringify({
          success: false,
          message: "You have already submitted a response",
        }),
      };
    }

    // Insert response with sanitized data

    return {
      statusCode: 200,
      headers: {
        "Content-Type": "application/json",
        "Access-Control-Allow-Origin": "*",
      },
      body: JSON.stringify({
        success: true,
        message: "Survey submitted successfully",
      }),
    };
  } catch (error) {
    console.error("Error processing survey submission:", error);
    return {
      statusCode: 500,
      headers: {
        "Content-Type": "application/json",
        "Access-Control-Allow-Origin": "*",
      },
      body: JSON.stringify({
        success: false,
        message: "Internal server error",
        details: error.message,
      }),
    };
  }
};
