"""Deterministic, title-based course tool recommendations shared by app paths."""

from __future__ import annotations

import argparse
import json
import re
import sys
import unicodedata
from pathlib import Path
from typing import Any


def _normalize_title(subject_title: str) -> str:
    normalized = unicodedata.normalize("NFKD", subject_title.casefold())
    normalized = "".join(character for character in normalized if not unicodedata.combining(character))
    normalized = normalized.replace("&", " and ")
    return " ".join(re.sub(r"[^a-z0-9]+", " ", normalized).split())


def _tool(
    name: str,
    reason: str,
    documentation: tuple[tuple[str, str], ...] = (),
) -> dict[str, Any]:
    return {
        "tool": name,
        "reason": reason,
        "documentation": [
            {"label": label, "url": url}
            for label, url in documentation
        ],
    }


_PROGRAMMING_DOCS = (
    ("Python documentation", "https://docs.python.org/3/"),
    ("Java learning", "https://dev.java/learn/"),
)
_EDITOR_DOCS = (("Visual Studio Code documentation", "https://code.visualstudio.com/docs"),)
_VERSION_CONTROL_DOCS = (
    ("Git documentation", "https://git-scm.com/doc"),
    ("GitHub documentation", "https://docs.github.com/"),
)
_PROJECT_BOARD_DOCS = (
    ("GitHub Projects documentation", "https://docs.github.com/en/issues/planning-and-tracking-with-projects/learning-about-projects/about-projects"),
)
_UML_DOCS = (("PlantUML documentation", "https://plantuml.com/"),)

_TOOL_RULES: tuple[tuple[tuple[str, ...], tuple[dict[str, Any], ...]], ...] = (
    (
        ("security", "secure", "cybersecurity", "information assurance", "forensic", "cryptography"),
        (
            _tool("OWASP Juice Shop", "Provides a deliberately vulnerable application for safe security testing exercises.", (("OWASP Juice Shop project", "https://owasp.org/www-project-juice-shop/"),)),
            _tool("Wireshark", "Supports inspection of security-relevant network traffic and protocols.", (("Wireshark documentation", "https://www.wireshark.org/docs/"),)),
            _tool("OWASP Top 10", "Provides a reference for common web application security risks.", (("OWASP Top 10 project", "https://owasp.org/www-project-top-ten/"),)),
        ),
    ),
    (
        ("database", "sql", "information management", "data management"),
        (
            _tool("PostgreSQL or MySQL", "Provides a relational database for schema design and SQL practice.", (("PostgreSQL documentation", "https://www.postgresql.org/docs/"), ("MySQL documentation", "https://dev.mysql.com/doc/"))),
            _tool("pgAdmin or MySQL Workbench", "Provides a visual client for querying and administering the selected database.", (("pgAdmin documentation", "https://www.pgadmin.org/docs/"), ("MySQL Workbench manual", "https://dev.mysql.com/doc/workbench/en/"))),
        ),
    ),
    (
        ("cloud", "virtualization", "virtual machine", "devops", "container", "deployment"),
        (
            _tool("Docker", "Supports repeatable containerized development and deployment exercises.", (("Docker documentation", "https://docs.docker.com/"),)),
            _tool("GitHub Actions", "Automates build and test workflows for course projects.", (("GitHub Actions documentation", "https://docs.github.com/actions"),)),
            _tool("AWS or Azure student environment", "Provides a managed environment for cloud exercises when available through the institution.", (("AWS Educate", "https://aws.amazon.com/education/awseducate/"), ("Azure for Students", "https://azure.microsoft.com/en-us/free/students/"))),
        ),
    ),
    (
        ("network", "networking", "routing", "switching", "internetwork", "network administration"),
        (
            _tool("Cisco Packet Tracer", "Simulates network configuration and topology exercises.", (("Cisco Packet Tracer", "https://www.netacad.com/courses/packet-tracer"),)),
            _tool("Wireshark", "Supports packet inspection and protocol troubleshooting.", (("Wireshark documentation", "https://www.wireshark.org/docs/"),)),
        ),
    ),
    (
        ("web", "html", "css", "javascript", "front end", "frontend", "mobile application"),
        (
            _tool("HTML, CSS, and JavaScript", "Provide the core technologies for building interactive web pages.", (("MDN Web Docs", "https://developer.mozilla.org/"),)),
            _tool("Visual Studio Code", "Supports editing and debugging web projects.", _EDITOR_DOCS),
            _tool("Browser developer tools", "Help inspect and troubleshoot rendered pages, scripts, and network requests.", (("Chrome DevTools documentation", "https://developer.chrome.com/docs/devtools/"),)),
        ),
    ),
    (
        ("operating system", "operating systems", "linux", "unix", "system administration"),
        (
            _tool("Ubuntu/Linux shell", "Supports command-line and system-administration practice.", (("Ubuntu tutorials", "https://ubuntu.com/tutorials"),)),
            _tool("VirtualBox", "Provides an isolated virtual machine for operating-system labs.", (("VirtualBox manual", "https://www.virtualbox.org/manual/"),)),
        ),
    ),
    (
        ("human computer interaction", "hci", "user experience", "usability", "interface design"),
        (
            _tool("Figma or paper prototypes", "Supports rapid interface mockups and usability iteration.", (("Figma Help Center", "https://help.figma.com/"),)),
            _tool("WAVE accessibility evaluation tool", "Helps identify accessibility issues in interface designs.", (("WAVE Web Accessibility Evaluation Tool", "https://wave.webaim.org/"),)),
        ),
    ),
    (
        ("multimedia", "digital media", "graphics", "animation", "audio", "video editing"),
        (
            _tool("GIMP", "Supports raster-image editing for visual media work.", (("GIMP documentation", "https://docs.gimp.org/"),)),
            _tool("Audacity", "Supports audio recording and editing.", (("Audacity manual", "https://manual.audacityteam.org/"),)),
            _tool("Blender", "Supports 3D modeling and animation.", (("Blender documentation", "https://docs.blender.org/"),)),
        ),
    ),
    (
        ("artificial intelligence", "machine learning", "data science", "data analytics", "analytics", "big data"),
        (
            _tool("Python", "Supports data-processing and model exercises.", (("Python documentation", "https://docs.python.org/3/"),)),
            _tool("Jupyter Notebook", "Supports interactive, reproducible data experiments.", (("Jupyter documentation", "https://docs.jupyter.org/"),)),
            _tool("pandas", "Supports tabular data cleaning and analysis.", (("pandas documentation", "https://pandas.pydata.org/docs/"),)),
            _tool("scikit-learn", "Supports standard machine-learning experiments and evaluation.", (("scikit-learn user guide", "https://scikit-learn.org/stable/user_guide.html"),)),
        ),
    ),
    (
        ("computer architecture", "computer organization", "logic circuit", "digital circuit", "microprocessor"),
        (
            _tool("Logisim-evolution", "Simulates logic circuits and digital systems.", (("Logisim-evolution project", "https://github.com/logisim-evolution/logisim-evolution"),)),
            _tool("Ripes", "Visualizes RISC-V processor execution and architecture concepts.", (("Ripes project", "https://github.com/mortbopet/Ripes"),)),
        ),
    ),
    (
        ("discrete math", "discrete mathematics", "discrete mathematical structures", "combinatorics", "graph theory"),
        (
            _tool("Python", "Supports small, reproducible graph and combinatorics examples.", (("Python documentation", "https://docs.python.org/3/"),)),
        ),
    ),
    (
        ("capstone", "thesis", "software project"),
        (
            _tool("Git and GitHub", "Preserves project history and supports collaborative review.", _VERSION_CONTROL_DOCS),
            _tool("GitHub Projects (issue/task board)", "Tracks project milestones, tasks, and defects.", _PROJECT_BOARD_DOCS),
            _tool("PlantUML (UML diagramming)", "Documents system structure and behavior.", _UML_DOCS),
        ),
    ),
    (
        ("systems analysis", "system analysis", "systems design", "system design", "project management", "software engineering"),
        (
            _tool("PlantUML (UML diagramming)", "Models system structure and behavior.", _UML_DOCS),
            _tool("GitHub Projects (issue/task board)", "Organizes requirements, tasks, and project work.", _PROJECT_BOARD_DOCS),
            _tool("Git and GitHub", "Records and supports review of project changes.", _VERSION_CONTROL_DOCS),
        ),
    ),
    (
        ("internship", "on the job training", "practicum", "work integrated learning"),
        (
            _tool("Placement-approved IDE", "Fits the assigned work while respecting the host organization's requirements."),
            _tool("Placement-approved source control/issue tracker", "Uses the host team's existing review and task workflow."),
        ),
    ),
    (
        ("introduction to computing", "computing fundamentals", "programming", "coding", "computer programming", "algorithm", "data structure", "software development"),
        (
            _tool("Python or Java", "Supports implementing and testing programming exercises.", _PROGRAMMING_DOCS),
            _tool("Visual Studio Code", "Supports editing, running, and debugging code.", _EDITOR_DOCS),
            _tool("Git and GitHub", "Supports version history and collaborative code review.", _VERSION_CONTROL_DOCS),
        ),
    ),
)


def recommend_tools(subject_title: str) -> list[dict[str, Any]]:
    """Return curated, title-based tool recommendations without external calls."""
    normalized_title = _normalize_title(subject_title)
    if normalized_title:
        padded_title = f" {normalized_title} "
        for keywords, recommendations in _TOOL_RULES:
            normalized_keywords = (_normalize_title(keyword) for keyword in keywords)
            if any(f" {keyword} " in padded_title for keyword in normalized_keywords):
                return [
                    {
                        **item,
                        "documentation": [
                            dict(documentation)
                            for documentation in item["documentation"]
                        ],
                    }
                    for item in recommendations
                ]

    return [
        _tool(
            "No title-based software recommendation",
            "The title alone does not identify a software task; follow the course syllabus and instructor-approved materials.",
        )
    ]


def main() -> None:
    parser = argparse.ArgumentParser(description="Map course titles to curated tool recommendations.")
    parser.add_argument("--titles-file", required=True, type=Path)
    args = parser.parse_args()
    try:
        titles = json.loads(args.titles_file.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise SystemExit(f"Unable to read course titles: {error}") from error
    if not isinstance(titles, list) or any(not isinstance(title, str) for title in titles):
        raise SystemExit("Course titles must be a JSON array of strings.")
    result = {
        title: recommend_tools(title)
        for title in dict.fromkeys(titles)
    }
    json.dump(result, sys.stdout, ensure_ascii=False)


if __name__ == "__main__":
    main()
