import os
import time
from flask import Flask, jsonify, request
from models import db, Notification
from dotenv import load_dotenv
from sqlalchemy.exc import OperationalError

load_dotenv()

app = Flask(__name__)

# Config database
db_host = os.getenv('DB_HOST', 'localhost')
db_name = os.getenv('DB_NAME', 'laundry_notification_db')
db_user = os.getenv('DB_USER', 'root')
db_password = os.getenv('DB_PASSWORD', 'root')
db_port = os.getenv('DB_PORT', '3306')

app.config['SQLALCHEMY_DATABASE_URI'] = f"mysql+pymysql://{db_user}:{db_password}@{db_host}:{db_port}/{db_name}"
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False

db.init_app(app)

# Create tables with retry to handle DB startup delays in Docker Compose
with app.app_context():
    for attempt in range(10):
        try:
            db.create_all()
            print("Notification Service database tables created/connected successfully.")
            break
        except OperationalError:
            print(f"Notification Database not ready, retrying in 3 seconds... ({attempt+1}/10)")
            time.sleep(3)

@app.route('/notifications', methods=['GET'])
def get_notifications():
    notifications = Notification.query.all()
    return jsonify([n.to_dict() for n in notifications]), 200

@app.route('/notifications/<int:id>', methods=['GET'])
def get_notification(id):
    notification = db.session.get(Notification, id)
    if not notification:
        return jsonify({'message': 'Notification not found'}), 404
    return jsonify(notification.to_dict()), 200

@app.route('/notifications', methods=['POST'])
def create_notification():
    data = request.get_json() or {}
    
    email = data.get('email')
    message = data.get('message')
    
    if not email or not message:
        return jsonify({'message': 'Email and message are required'}), 400
        
    user_id = data.get('user_id')
    status = data.get('status', 'sent')
    
    notification = Notification(
        user_id=user_id,
        email=email,
        message=message,
        status=status
    )
    db.session.add(notification)
    db.session.commit()
    
    # Print simulation log
    print(f"\n[NOTIFICATION LOG] [{time.strftime('%Y-%m-%d %H:%M:%S')}] Sending email to {email}: {message}\n", flush=True)
    
    return jsonify({
        'message': 'Notification created and sent successfully',
        'data': notification.to_dict()
    }), 201

@app.route('/notifications/<int:id>', methods=['DELETE'])
def delete_notification(id):
    notification = db.session.get(Notification, id)
    if not notification:
        return jsonify({'message': 'Notification not found'}), 404
    db.session.delete(notification)
    db.session.commit()
    return jsonify({'message': 'Notification deleted successfully'}), 200

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
