# MegaLOD Platform - Installation Guide

This guide details the complete installation of the MegaLOD platform for managing and visualizing archaeological data.

### Base Software
- **Apache** 2.4+ (with AllowOverride "All" and mod_rewrite enabled)
- **MySQL** 5.7.9+
- **PHP** 7.4+ (latest stable version preferred)
- **Node.js** and **npm**
- **ImageMagick** 6.7.5+ (for thumbnail generation)

---

# 🛠️ How to Install XAMPP and MySQL

XAMPP is an all-in-one package that includes the **Apache** web server and **MariaDB** (a variant of MySQL). By installing XAMPP, you install the database server you need.

---

## 1. Download XAMPP

1.  Navigate to the official [Apache Friends website](https://www.apachefriends.org/download.html).
2.  Download the **XAMPP installer** for your operating system (**Windows**, **Linux**, or **OS X**).

---

## 2. Run the Installer

1.  **Run the downloaded file** (e.g., `xampp-windows-x64-*.exe`).
2.  You may see a **UAC warning**; click **OK** or **Yes** to continue.
3.  On the **Select Components** screen, ensure that the **MySQL** component is **checked**.
4.  Choose your installation folder. The default is usually `C:\xampp`.
5.  Click **Next** through the remaining steps and then **Install**.
6.  Once installation is complete, check the box to **Start the Control Panel now** and click **Finish**.

---

## 3. Start the MySQL Service

1.  The **XAMPP Control Panel** will open.
2.  Locate the **Apache** module and click the **Start** button next to it.
3.  Locate the **MySQL** module and click the **Start** button next to it.
4.  The status text for both should turn **green**, indicating the web server and database server are running.

---

## 4. Access phpMyAdmin

You can manage your MySQL databases through the web interface **phpMyAdmin**, which is included with XAMPP.

1.  In the XAMPP Control Panel, click the **Admin** button next to the **MySQL** module.
2.  Your default web browser will open to the address `http://localhost/phpmyadmin/`.
3.  You can now create and manage your MySQL databases using this interface.

# How to Install Omeka S

To install Omeka S, now that you have the database defined, clone this repository and place the folder omeka-test-version (rename to omeka-s) in the "xampp/htdocs" directory. Then check the main folder of this project for the official installation guide for Omeka S and how to connect the app to the database. Do not forget to install all omeka-s modules available inside the source code.

# 🐘 GraphDB Installation Guide

**GraphDB** is a high-performance semantic graph database (triplestore). For local development, the simplest method is to use its **standalone distribution**, which includes an embedded web server (Jetty) and does not require manual configuration with XAMPP's Tomcat.

---

## 1. Prerequisites (Install Java)

GraphDB is a Java application and requires a **Java Runtime Environment (JRE)** or **Java Development Kit (JDK)** to run.

1.  **Check Java Version:** Open your terminal or command line and run:
    ```bash
    java -version
    ```
2.  **Install Java:** If Java is not installed, download and install the latest stable **Java JDK** (version 8 or newer is usually sufficient) from Oracle or Adoptium.

---

## 2. Download and Extract GraphDB

1.  **Download:** Visit the official Ontotext GraphDB download page (search for "GraphDB Free download").
2.  **Extract:** Download the **GraphDB Free** ZIP file and extract its contents to a simple location on your machine, such as:
    * **Windows:** `C:\GraphDB`
    * **Linux/macOS:** `/Users/YourName/GraphDB`

---

## 3. Run the GraphDB Server

The server is started by opening the graphdb app.

---

## 4. Access the GraphDB Workbench

The Workbench is the web-based user interface for managing your database.

1.  **Open in Browser:** Open your web browser and navigate to:
    ```
    http://localhost:7200
    ```
2.  **Create a Repository:**
    * In the Workbench, go to **Setup** > **Repositories**.
    * Click the **Create new repository** button.
    * Choose a repository type (e.g., **OWL-Horst** is common for inferencing).
    * Provide a unique **Repository ID** (e.g., `omeka_repo`).
    * Click **Create**.

---

## 5. Connecting GraphDB to Omeka S

Integration uses the custom **AddTriplestore** module. Enable it under **Omeka S Admin → Modules**, then set **GraphDB URL, repository, credentials, and MegaLOD base URIs** in `modules/AddTriplestore/config/graphdb.config.php` (copy from `graphdb.config.php.dist`) and/or the environment variables documented in `modules/AddTriplestore/README.md` — there is no separate GraphDB settings screen in the admin UI for these values.

Typical **local** values (adjust to match your GraphDB workbench):

| Parameter | Value |
| :--- | :--- |
| **GraphDB Server URL** | `http://localhost:7200` |
| **Repository ID** | `omeka_repo` (or the ID you created in Step 4) |
| **User/Password** | As required by your GraphDB instance (often set for non-local deployments) |


# Final steps

Finally, after everything running, add the vocabularies to your omeka-s interface in the dashboard and configure all the permissions and change any local variables in the code, specially the key to omeka.

