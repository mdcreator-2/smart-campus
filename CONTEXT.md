# Smart Campus Issue Portal – Project Context

## Project Summary
A campus web application where hostel students can raise issues, upload mandatory photos (1–4), and describe ongoing problems. Other students can upvote/downvote issues, and a point/badge system tracks student engagement. Admins resolve/reject/archive issues and manage the overall workflow. Rejected issues can be rebutted by other students. The project will eventually scale from hostel-only to the entire campus.

## Main Features
- Student issue reporting with category, description, and photos (at least 1, up to 4)
- Public feed showing all issues with sorting (default: mix of newest/most-voted)
- Each user can upvote or downvote any issue (one active vote per user per issue, can change anytime)
- Admin-only actions: resolve/reject/archive issues; assign points on resolution
- Points and badge system (custom badges and student-themed ranks)
- Option for students to file rebuttals for rejected issues
- Issues and votes are tracked anonymously if opted in by user, but badges and ranks remain visible
- Login/registration with option for anonymous issue reporting

## Technologies Used
- PHP (backend)
- MySQL (database, see schema file)
- HTML/CSS/JavaScript (frontend, Bootstrap for design)
- Images uploaded (and stored with filenames/paths in database)
- GitHub for version control
- Deployed initially for hostel use; later general campusization

## User Roles
- **Student/User**: Posts issues, uploads photos, votes, tracks points/rank, can be anonymous
- **Admin**: Reviews/issues, changes status (open, resolved, rejected, archived), controls points, can reject issues

## Key Project Structure
- `/public/` – Entry point(s) for frontend (HTML, assets)
- `/app/` – Controllers, models, backend logic
- `/config/` – App and DB config
- `/uploads/` – Uploaded images
- `/docs/` – Extra documentation

## Naming/Design Conventions
- All user-facing content is student-centric and “campus hostel” themed
- Voting, filtering, ranking, and point mechanisms are transparent to users
- Issue and photo handling use incremental IDs and time stamps
- Anonymous submissions do not display name/hostel/roll info to peers
- Admins can view all user attribution, regardless of anonymity setting

## Coding Guidelines & Practices
- Backend code in PHP 8+, modular controller/model separation
- Use prepared statements/parameterized queries
- Frontend in semantic HTML5, Bootstrap, minimal Reactivity via JS (no SPA needed)
- Comments for all functions and complex logic blocks
- Regular Git commits with descriptive messages
- All sensitive credentials/configs excluded from commits (see .gitignore)

## Contextual Prompts for Copilot/AI
- Reference the current project structure and objective as described here
- When autocompleting, prioritize student-friendly messages and ease of use
- If a feature/task requires database access, use the structure described in `/docs/db_schema.sql`
- All authentication, voting, and issue interaction logic must respect role-based access control

## To-Do & Experimentation
- MVP: Hostel-focused, room-level reporting
- Future: Comments/discussions on issues (not yet implemented)
- Future: AI-based duplicate issue detection
- Future: Notifications/real-time updates

---

_Last updated: Nov 2025. For any new feature, add a section to this file as needed._
