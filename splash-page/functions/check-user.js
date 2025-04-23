const { MongoClient } = require("mongodb");

// Replace with your MongoDB connection string
const uri = process.env.MONGODB_URI;

exports.handler = async (event, context) => {
  // Get IP address from request
  const ip = event.headers["x-forwarded-for"] || event.headers["client-ip"];

  const client = new MongoClient(uri);

  try {
    await client.connect();
    const database = client.db("splash_survey");
    const collection = database.collection("survey_responses");

    // Check if IP exists in database
    const count = await collection.countDocuments({ ip_address: ip });

    return {
      statusCode: 200,
      body: JSON.stringify({ has_submitted: count > 0 }),
    };
  } catch (error) {
    return {
      statusCode: 500,
      body: JSON.stringify({ error: error.message }),
    };
  } finally {
    await client.close();
  }
};
