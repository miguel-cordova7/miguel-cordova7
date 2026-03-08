import os
import requests
from datetime import datetime, timedelta

# Configuración
USERNAME = "miguel-cordova7"
TOKEN = os.getenv("GH_TOKEN")
HEADERS = {"Authorization": f"Bearer {TOKEN}"}

def get_contributions():
    query = """
    query($userName:String!) {
      user(login: $userName){
        contributionsCollection {
          contributionCalendar {
            totalContributions
            weeks {
              contributionDays {
                contributionCount
                date
              }
            }
          }
        }
      }
    }
    """
    variables = {"userName": USERNAME}
    response = requests.post(
        "https://api.github.com/graphql",
        json={"query": query, "variables": variables},
        headers=HEADERS
    )
    if response.status_code != 200:
        raise Exception(f"Error en la API: {response.status_code}")
    return response.json()

def format_date(d):
    # Elimina el cero a la izquierda en los días (ej. Mar 07 -> Mar 7)
    return d.strftime("%b %d").replace(" 0", " ")

def calculate_streaks(data):
    calendar = data['data']['user']['contributionsCollection']['contributionCalendar']
    total = calendar['totalContributions']

    days = []
    for week in calendar['weeks']:
        for day in week['contributionDays']:
            day['date_obj'] = datetime.strptime(day['date'], "%Y-%m-%d").date()
            days.append(day)

    days.sort(key=lambda x: x['date_obj'])

    current_streak = 0
    current_streak_date = None
    longest_streak = 0
    longest_streak_start = None
    longest_streak_end = None
    temp_streak = 0
    temp_streak_start = None

    today = datetime.now().date()
    yesterday = today - timedelta(days=1)

    for day in days:
        if day['contributionCount'] > 0:
            if temp_streak == 0:
                temp_streak_start = day['date_obj']
            temp_streak += 1
            if temp_streak > longest_streak:
                longest_streak = temp_streak
                longest_streak_start = temp_streak_start
                longest_streak_end = day['date_obj']
        else:
            temp_streak = 0
            temp_streak_start = None

    days_reversed = list(reversed(days))
    for i, day in enumerate(days_reversed):
        if i == 0 and day['contributionCount'] == 0:
            continue
        if day['contributionCount'] > 0:
            if current_streak == 0:
                current_streak_date = day['date_obj']
            current_streak += 1
        else:
            break

    calendar_start_date = days[0]['date_obj']
    
    if current_streak_date:
        current_date_formatted = format_date(datetime.now())
    else:
        last_commit_day = next((d for d in days_reversed if d['contributionCount'] > 0), None)
        if last_commit_day:
             current_date_formatted = format_date(last_commit_day['date_obj'])
        else:
             current_date_formatted = format_date(yesterday)

    def format_date_range(start, end):
        if not start or not end:
            return ""
        if start.year == end.year:
             return f"{format_date(start)} - {format_date(end)}"
        else:
             return f"{format_date(start)}, {start.year} - {format_date(end)}, {end.year}"

    total_date_range = f"{format_date(calendar_start_date)}, {calendar_start_date.year} - Present"
    longest_streak_range = format_date_range(longest_streak_start, longest_streak_end)

    return total, current_streak, longest_streak, total_date_range, current_date_formatted, longest_streak_range

def generate_svg(total, current, longest, total_range, current_date, longest_range):
    # Diseño clonado de la referencia: Anillo con abertura matemática perfecta y Octicon de fuego
    svg_template = f"""<svg xmlns="http://www.w3.org/2000/svg" width="495" height="195">
        <style>
            .stat {{ font: 700 32px 'Segoe UI', Ubuntu, sans-serif; }}
            .label {{ font: 700 14px 'Segoe UI', Ubuntu, sans-serif; }}
            .date {{ font: 400 12px 'Segoe UI', Ubuntu, sans-serif; fill: #A3B3BC; }}
        </style>
        
        <rect width="495" height="195" fill="#0d1117" rx="4.5" />

        <line x1="165" y1="45" x2="165" y2="170" stroke="#E4E2E2" stroke-opacity="0.2" stroke-width="1.5" />
        <line x1="330" y1="45" x2="330" y2="170" stroke="#E4E2E2" stroke-opacity="0.2" stroke-width="1.5" />

        <text x="82.5" y="100" fill="#00a8f3" class="stat" text-anchor="middle">{total}</text>
        <text x="82.5" y="136" fill="#00a8f3" class="label" text-anchor="middle">Total Contributions</text>
        <text x="82.5" y="160" class="date" text-anchor="middle">{total_range}</text>

        <circle cx="247.5" cy="88" r="38" fill="none" stroke="#00a8f3" stroke-width="4.5" stroke-linecap="round" stroke-dasharray="198.76 40" stroke-dashoffset="-20" transform="rotate(-90 247.5 88)" />
        
        <svg x="235.5" y="38" width="24" height="24" viewBox="0 0 24 24" fill="#00a8f3">
            <path d="M11.83 1.018a.75.75 0 0 1 .632.253c.693.858 1.487 1.947 2.112 3.232.613 1.259 1.026 2.651 1.026 4.097 0 1.284-.33 2.502-.95 3.526a.75.75 0 0 1-1.285-.754A5.5 5.5 0 0 0 14.1 8.6c0-1.22-.352-2.39-.884-3.486-.54-1.11-1.233-2.062-1.83-2.81a8.498 8.498 0 0 0-.256-.307c-.12.213-.263.456-.425.728a17.846 17.846 0 0 0-1.463 2.87c-.633 1.543-1.242 3.473-1.242 5.505 0 2.222.846 4.343 2.378 5.89A8.04 8.04 0 0 0 16.033 19.1a.75.75 0 0 1 .536 1.398 9.539 9.539 0 0 1-6.725-2.227C7.994 16.402 7 13.824 7 11.1c0-2.261.68-4.398 1.373-6.084a19.344 19.344 0 0 1 1.636-3.149l.06-.098.026-.04a.75.75 0 0 1 .562-.317l.023-.001.034.001.027.003c.097.013.256.046.474.122.387.135.845.337 1.353.626Zm-1.57.915a13.385 13.385 0 0 0-1.144 2.179A13.235 13.235 0 0 0 8.5 8.6c0 2.333.805 4.5 2.14 6.007a8.038 8.038 0 0 0 4.395 2.39.75.75 0 0 1-.223 1.483 9.538 9.538 0 0 1-5.186-2.827C8.114 14.28 7 11.666 7 8.6c0-1.841.486-3.69 1.13-5.22a14.885 14.885 0 0 1 1.05-1.928.75.75 0 0 1 1.08-.23c.365.234.78.47 1.25.7Zm5.688 8.868a.75.75 0 0 1 .989-.356C18.663 11.196 19.5 12.878 19.5 14.6c0 1.956-.78 3.822-2.183 5.225A7.538 7.538 0 0 1 12 22a7.539 7.539 0 0 1-5.317-2.175.75.75 0 0 1 1.06-1.06 6.039 6.039 0 0 0 4.257 1.735 6.038 6.038 0 0 0 4.257-1.735c1.123-1.123 1.743-2.616 1.743-4.165 0-1.341-.645-2.67-1.36-3.376a.75.75 0 0 1-.356-.99Z"></path>
        </svg>
        
        <text x="247.5" y="100" fill="#28a745" class="stat" text-anchor="middle">{current}</text>
        <text x="247.5" y="136" fill="#28a745" class="label" text-anchor="middle">Current Streak</text>
        <text x="247.5" y="160" class="date" text-anchor="middle">{current_date}</text>

        <text x="412.5" y="100" fill="#00a8f3" class="stat" text-anchor="middle">{longest}</text>
        <text x="412.5" y="136" fill="#00a8f3" class="label" text-anchor="middle">Longest Streak</text>
        <text x="412.5" y="160" class="date" text-anchor="middle">{longest_range}</text>
    </svg>"""
    
    with open("racha.svg", "w", encoding="utf-8") as file:
        file.write(svg_template)

if __name__ == "__main__":
    if not TOKEN:
        print("Error: No se encontró el token GH_TOKEN.")
    else:
        try:
             data = get_contributions()
             total, current, longest, total_range, current_date, longest_range = calculate_streaks(data)
             generate_svg(total, current, longest, total_range, current_date, longest_range)
             print("¡Archivo racha.svg generado con éxito!")
        except Exception as e:
             print(f"Ocurrió un error al generar el SVG: {e}")
