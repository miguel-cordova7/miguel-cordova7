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
    # Elimina el cero a la izquierda en los días
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
    total_str = f"{total:,}"
    current_str = f"{current:,}"
    longest_str = f"{longest:,}"

    svg_template = f"""<svg xmlns="http://www.w3.org/2000/svg" width="495" height="195">
        <style>
            .stat {{ font: 700 32px 'Segoe UI', Ubuntu, sans-serif; }}
            .label {{ font: 600 14px 'Segoe UI', Ubuntu, sans-serif; }}
            .date {{ font: 400 12px 'Segoe UI', Ubuntu, sans-serif; fill: #fff; }}
        </style>
        
        <rect width="495" height="195" fill="#0d1117" rx="4.5" />

        <line x1="165" y1="45" x2="165" y2="155" stroke="#E4E2E2" stroke-opacity="0.2" stroke-width="1.5" />
        <line x1="330" y1="45" x2="330" y2="155" stroke="#E4E2E2" stroke-opacity="0.2" stroke-width="1.5" />

        <text x="82.5" y="95" fill="#00a8f3" class="stat" text-anchor="middle">{total_str}</text>
        <text x="82.5" y="130" fill="#00a8f3" class="label" text-anchor="middle">Total Contributions</text>
        <text x="82.5" y="155" class="date" text-anchor="middle">{total_range}</text>

        <path d="M 225.5 51.2 A 42 42 0 1 0 269.5 51.2" fill="none" stroke="#00a8f3" stroke-width="4.5" stroke-linecap="round" />
        
        <g transform="translate(233.1, 24.5) scale(1.2)">
            <path fill="#00a8f3" d="M11.5 22.04c-1.35 0-2.62-.52-3.57-1.45-.96-.93-1.48-2.22-1.46-3.57.03-2.12 1.13-3.9 2.1-5.47.41-.66.81-1.31 1.15-2.03.4-1.03.51-2.13.36-3.26-.01-.04-.03-.07-.05-.1.18-.2.43-.3.7-.3.26 0 .51.1.7.3.16.35.33.7.53 1.05.5 1.05 1.1 2.15 1.83 3.06.75.95 1.63 1.82 2.45 2.77 1.05 1.25 1.7 2.8 1.68 4.4-.01 1.4-.55 2.72-1.53 3.67-.98.98-2.31 1.52-3.71 1.52zm-1.63-4.5c.23.25.55.38.88.38.33 0 .65-.13.88-.38.21-.23.33-.53.31-.85 0-.25-.08-.48-.23-.67l-1.36-1.58c-.38-.43-.61-.98-.68-1.55-.03-.55.1-1.08.35-1.55l.06-.13c-.23.37-.45.75-.66 1.12-.38.63-.78 1.3-1.15 2.02-.18.35-.35.72-.48 1.12-.18.58-.25 1.18-.16 1.78.08.52.31.98.66 1.37z" />
        </g>
        
        <text x="247.5" y="105" fill="#28a745" class="stat" text-anchor="middle">{current_str}</text>
        <text x="247.5" y="145" fill="#28a745" class="label" text-anchor="middle">Current Streak</text>
        <text x="247.5" y="165" class="date" text-anchor="middle">{current_date}</text>

        <text x="412.5" y="95" fill="#00a8f3" class="stat" text-anchor="middle">{longest_str}</text>
        <text x="412.5" y="130" fill="#00a8f3" class="label" text-anchor="middle">Longest Streak</text>
        <text x="412.5" y="155" class="date" text-anchor="middle">{longest_range}</text>
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
