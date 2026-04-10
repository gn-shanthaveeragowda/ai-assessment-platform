# ai-assessment-platform
> An AI-powered assessment portal featuring dynamic content generation and NLP-based descriptive grading to eliminate administrative burnout.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![Generative AI](https://img.shields.io/badge/Generative_AI-FF6F00?style=for-the-badge&logo=google&logoColor=white)

## Project Overview
The Automated Quiz Evaluation System is a full-stack N-Tier web application designed to optimize the academic assessment lifecycle. By leveraging a Pre-trained Large Language Model (LLM) via API, the system dynamically generates topic-specific question banks using Zero-Shot Inference. It also features a custom Natural Language Processing (NLP) pipeline to automatically evaluate and grade descriptive student answers using semantic keyword matching and a definitive decision boundary.

## Core Engineering Features
* **Generative AI Content Pipeline:** Automated creation of MCQs and descriptive questions using predictive modeling, eliminating the need for a static training dataset.
* **Intelligent Evaluation Engine:** Automated grading of descriptive answers utilizing an NLP pipeline (Tokenization, Stop-word Removal) and Regex-based semantic mapping.
* **Algorithmic Fairness:** Implements the Fisher-Yates shuffling algorithm to randomize question order, ensuring unique test instances for every student to prevent bias and cheating.
* **Role-Based Access Control (RBAC):** Secure, distinct dashboard interfaces for Students, Teachers, and University Administrators.
* **Semantic Difficulty Scaling:** Dynamic adjustment of question complexity (Easy, Medium, Hard) by constraining the AI's search depth and targeting mid-range to low-frequency technical concepts.

## System Architecture
* **Frontend Tier:** HTML5, CSS3, Tailwind CSS, Vanilla JavaScript
* **Business Logic Layer:** PHP Application Server
* **Data Layer:** MySQL (Relational Schema)
* **External API Tier:** LLM endpoint for NLP processing and generative inference

## The Machine Learning Lifecycle
1. **Generative Inference:** A teacher inputs a topic. The system uses prompt engineering to instruct the LLM to generate a structured dataset (JSON) of questions, distractors, and an evaluation keyword cloud.
2. **Data Orchestration:** The generated content is parsed and persistently stored in the local MySQL database.
3. **Attempt & Constraint:** Students access a randomized quiz interface governed by a live timer and heuristic gating (e.g., a minimum character limit to prevent low-effort submissions).
4. **Discriminative Classification:** The PHP backend cleans the student's submitted text and applies a strict mathematical threshold (Score >= 40%) against the AI-generated keywords to classify the descriptive answer as Correct or Incorrect.

## Installation & Deployment
Follow these steps to run the project locally for development or demonstration:

1. **Clone the repository:**
   ```bash
   git clone https://github.com/gn-shanthaveeragowda/ai-assessment-platform.git

## Team Members
Developed as a 6th Semester B.E. Computer Science Engineering project at Bangalore Technological Institute, affiliated with Visvesvaraya Technological University (VTU).

* **@gn-shanthaveeragowda** - Backend Logic & AI Integration
* **@chidananda-p** - Frontend & UI/UX Development
* **@basavarajholimath7676-cmd** - Database Design & Orchestration
* **manoj-somayya-mathad** - Testing, QA, & Documentation
